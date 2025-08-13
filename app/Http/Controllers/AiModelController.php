<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Services\ChatService;
use Inertia\Inertia;
use App\Models\ProviderIcon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

use Illuminate\Http\Request;

class AiModelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $models = AiModel::with('providerIcon')->orderBy('name')->get();
        return Inertia::render('Ask/Index', [
            'models' => $models
        ]);
    }

    public function list(): JsonResponse
    {
        $models = AiModel::with('providerIcon')->orderBy('name')->get();
        return response()->json(
            [
                'count' => $models->count(),
                'data' => $models,
            ]
        );
    }

    public function populateNotAuthTwo(): JsonResponse
    {

        try {
            //$reponse = Http::get('https://openrouter.ai/api/frontend/all-providers');

            $reponse = Http::get('https://openrouter.ai/api/frontend/models');

            if (!$reponse->successful()) {
                logger()->warning('Erreur lors de la recup des models', ['status' => $reponse->status(), 'body' => $reponse->body()]);
                return response()->json(['message' => 'Erreur lors de la récupération des modèles'], $reponse->status());
            }

            $json = $reponse->json();
            if (!isset($json['data']) || !is_array($json['data'])) {
                logger()->error('Erreur lors de la recup des models', [
                    'status' => $reponse->status(),
                    'body' => $reponse->body(),
                ]);
                return response()->json(['message' => 'Format de données incorrect'], 500);
            }

            $i = 0;
            foreach ($json['data'] as $model) {
                $providerName = null;
                if (isset($model['endpoint']['provider_name'])) {
                    $providerName = $model['endpoint']['provider_name'];
                } else {
                    $split = explode('/', $model['slug']);
                    $providerName = $split[0];
                }
                if (empty($providerName))
                    $providerName = 'Unknown';
                AiModel::updateOrCreate(
                    ['model_id' => $model['slug']],
                    [
                        'name' => $model['name'],
                        'provider_name' => $providerName,
                        'context_length' => $model['context_length'] ?? null,
                        'max_completion_tokens' => $model['top_provider']['max_completion_tokens'] ?? null,
                        'pricing' => $model['endpoint']['pricing'] ?? null,
                        'is_active' => true,
                    ]
                );
                $i++;
            }
            cache()->forget('openrouter.models');
            return response()->json(['message' => $i . " enregistrements ajouté a la db"], 200);
        } catch (\Exception $e) {
            logger()->error(
                "Erreur lors de la population de AiModels",
                [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return response()->json(['message' => 'Erreur lors de la population des modèles: ' . $e->getMessage()], 500);
        }
    }

    public function populateNotAuth(): JsonResponse
    {
        try {
            // Providers: map slug->name et name->icon
            $providersRes = Http::get('https://openrouter.ai/api/frontend/all-providers');
            if (!$providersRes->successful()) {
                logger()->warning('Erreur providers', ['status' => $providersRes->status(), 'body' => $providersRes->body()]);
                return response()->json(['message' => 'Erreur providers'], $providersRes->status());
            }
            $providersJson = $providersRes->json();
            $slugToName = [];
            $nameToIcon = [];

            foreach (($providersJson['data'] ?? []) as $p) {
                if (!isset($p['name'])) continue;
                $name = $p['name'];
                $slug = $p['slug'] ?? null;

                if (!empty($p['icon']['url'])) {
                    $iconUrl = $p['icon']['url'];
                    if (str_starts_with($iconUrl, '/images/icons/')) {
                        $iconUrl = 'https://openrouter.ai' . $iconUrl;
                    }
                    $nameToIcon[strtolower($name)] = $iconUrl;
                }
                if ($slug) $slugToName[strtolower($slug)] = $name;

                // upsert ProviderIcon (name + icon)
                if (isset($nameToIcon[strtolower($name)])) {
                    ProviderIcon::updateOrCreate(
                        ['name' => $name],
                        ['url' => $nameToIcon[strtolower($name)]]
                    );
                }
            }

            // Fallback garanti
            ProviderIcon::updateOrCreate(
                ['name' => 'Huggingface'],
                ['url' => 'https://t0.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&url=https://huggingface.co/&size=256']
            );

            // 2) Models (catalogue complet)
            $reponse = Http::get('https://openrouter.ai/api/frontend/models');
            if (!$reponse->successful()) {
                logger()->warning('Erreur models', ['status' => $reponse->status(), 'body' => $reponse->body()]);
                return response()->json(['message' => 'Erreur lors de la récupération des modèles'], $reponse->status());
            }
            $json = $reponse->json();
            if (!isset($json['data']) || !is_array($json['data'])) {
                logger()->error('Format de données incorrect', ['body' => $reponse->body()]);
                return response()->json(['message' => 'Format de données incorrect'], 500);
            }

            $dbIconNamesLc = ProviderIcon::pluck('name')->map(fn($n) => strtolower($n))->toArray();
            $i = 0;

            foreach ($json['data'] as $model) {
                // providerSlug fiable
                $pSlug = $model['endpoint']['provider_slug'] ?? null;
                if (!$pSlug && !empty($model['slug'])) {
                    $pSlug = explode('/', $model['slug'])[0] ?? null;
                }
                $providerName = null;

                if ($pSlug) {
                    $key = strtolower(explode('/', $pSlug)[0]); // ex: "z-ai/fp8" -> "z-ai"
                    if (isset($slugToName[$key])) {
                        $providerName = $slugToName[$key];
                    }
                }

                // fallback via display name si nécessaire
                if (!$providerName) {
                    $candidate =
                        $model['endpoint']['provider_name']
                        ?? ($model['endpoint']['provider_info']['name'] ?? null)
                        ?? ($model['endpoint']['provider_display_name'] ?? null);
                    if ($candidate && in_array(strtolower($candidate), $dbIconNamesLc, true)) {
                        $providerName = ProviderIcon::whereRaw('LOWER(name) = ?', [strtolower($candidate)])->value('name');
                    }
                }

                if (!$providerName) $providerName = 'Huggingface';

                AiModel::updateOrCreate(
                    ['model_id' => $model['slug']],
                    [
                        'name' => $model['name'],
                        'provider_name' => $providerName, // FK -> provider_icons.name
                        'context_length' => $model['context_length'] ?? null,
                        'max_completion_tokens' => $model['endpoint']['max_completion_tokens'] ?? null,
                        'pricing' => $model['endpoint']['pricing'] ?? null,
                        'is_active' => true,
                    ]
                );
                $i++;
            }

            cache()->forget('openrouter.models');
            return response()->json(['message' => $i . " enregistrements ajouté a la db"], 200);
        } catch (\Exception $e) {
            logger()->error("Erreur lors de la population de AiModels", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur lors de la population des modèles: ' . $e->getMessage()], 500);
        }
    }

    public function populate(): JsonResponse
    {
        try {
            $reponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.openrouter.api_key'),
            ])->get(config('services.openrouter.base_url') . '/models');

            if (!$reponse->successful()) {
                logger()->warning('Erreur lors de la recup des models', ['status' => $reponse->status(), 'body' => $reponse->body()]);
                return response()->json(['message' => 'Erreur lors de la récupération des modèles'], $reponse->status());
            }

            $json = $reponse->json();
            if (!isset($json['data']) || !is_array($json['data'])) {
                logger()->error('Erreur lors de la recup des models', [
                    'status' => $reponse->status(),
                    'body' => $reponse->body(),
                ]);
                return response()->json(['message' => 'Format de données incorrect'], 500);
            }

            $i = 0;
            foreach ($json['data'] as $model) {
                $split = explode(':', $model['name']);
                $providerName = $split[0] ?? 'unknown';
                AiModel::updateOrCreate(
                    ['model_id' => $model['id']],
                    [
                        'name' => $model['name'],
                        'provider_name' => $providerName,
                        'context_length' => $model['context_length'] ?? null,
                        'max_completion_tokens' => $model['top_provider']['max_completion_tokens'] ?? null,
                        'pricing' => $model['pricing'] ?? null,
                        'is_active' => true,
                    ]
                );
                $i++;
            }
            cache()->forget('openrouter.models');
            return response()->json(['message' => $i . " enregistrements ajouté a la db"], 200);
        } catch (\Exception $e) {
            logger()->error(
                "Erreur lors de la population de AiModels",
                [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return response()->json(['message' => 'Erreur lors de la population des modèles: ' . $e->getMessage()], 500);
        }
    }

    public function populateFromApiGetModels(): JsonResponse
    {
        try {
            // 1) Providers (slug -> display name) + icônes absolues
            $providersRes = Http::get('https://openrouter.ai/api/frontend/all-providers');
            if (!$providersRes->successful()) {
                return response()->json(['message' => 'Erreur providers'], $providersRes->status());
            }
            $providers = $providersRes->json('data') ?? [];
            $slugToName = [];

            foreach ($providers as $provider) {
                $name = $provider['name'] ?? null;
                if (!$name) continue;

                $slug = $provider['slug'] ?? null;
                $iconUrl = $provider['icon']['url'] ?? null;
                if ($iconUrl && str_starts_with($iconUrl, '/images/icons/')) {
                    $iconUrl = 'https://openrouter.ai' . $iconUrl;
                }
                if ($iconUrl) {
                    ProviderIcon::updateOrCreate(['name' => $name], ['url' => $iconUrl]);
                }
                if ($slug)
                    $slugToName[strtolower($slug)] = $name;
            }

            //HugingFace si probleme
            ProviderIcon::updateOrCreate(
                ['name' => 'Huggingface'],
                ['url' => 'https://t0.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&url=https://huggingface.co/&size=256']
            );

            //Modeles dispo via clé (getModels)
            $apiModels = (new ChatService())->getModels(); // id, name, context_length, max_completion_tokens, pricing
            $idsKept = [];
            $i = 0;

            foreach ($apiModels as $model) {
                $id = $model['id'] ?? null; // ex: openai/gpt-4.1-mini
                if (!$id)
                    continue;

                // Provider via slug du modèle
                $slug = explode('/', $id)[0] ?? null;
                $providerName = $slug ? ($slugToName[strtolower($slug)] ?? null) : null;

                // Fallback: "OpenAI: xxx" -> "OpenAI" si présent dans provider_icons
                if (!$providerName) {
                    $candidate = trim(explode(':', $model['name'])[0] ?? '');
                    if ($candidate && ProviderIcon::where('name', $candidate)->exists()) {
                        $providerName = $candidate;
                    }
                }
                if (!$providerName)
                    $providerName = 'Huggingface';

                AiModel::updateOrCreate(
                    ['model_id' => $id],
                    [
                        'name' => $model['name'],
                        'provider_name' => $providerName, // FK -> provider_icons.name
                        'context_length' => $model['context_length'] ?? null,
                        'max_completion_tokens' => $model['max_completion_tokens'] ?? null,
                        'pricing' => $model['pricing'] ?? null,
                        'is_active' => true,
                    ]
                );

                $idsKept[] = $id;
                $i++;
            }

            // 3) Désactive en DB ce qui n’est pas dispo pour TA clé
            if (!empty($idsKept)) {
                AiModel::whereNotIn('model_id', $idsKept)->update(['is_active' => false]);
            }

            cache()->forget('openrouter.models');
            return response()->json(['message' => $i . ' modèles importés/maj (clé API) + mapping providers'], 200);
        } catch (\Exception $e) {
            logger()->error('Erreur populateFromApiGetModels', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    public function nullproviderIcons(): JsonResponse
    {
        try {
            $modelsWithoutIcons = AiModel::whereDoesntHave('providerIcon')->get();
            // $models = AiModel::with('providerIcon')->orderBy('name')->get();

            $i = 0;
            foreach ($modelsWithoutIcons as $model) {
                $model->update(['provider_name' => 'Huggingface']);
                $i++;
            }

            cache()->forget('openrouter.models');

            return response()->json(['message' => $i . 'models a provider_name NULL mis a Huggingface'], 200);
        } catch (\Exception $e) {
            logger()->error(
                "Erreur lors de l'update de la db AiModels",
                [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return response()->json(['message' => "Erreur lors de l'update de la db AiModels " . $e->getMessage()], 500);
        }
    }


    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'model_id' => 'required|string|unique:ai_models',
                'name' => 'required|string',
                'provider_name' => 'required|string|exists:provider_icons,name',
                'context_length' => 'nullable|integer',
                'max_completion_tokens' => 'nullable|integer',
                'pricing' => 'nullable|json',
                'is_active' => 'boolean',
            ]
        );

        $model = AiModel::create($validated);
        cache()->forget('openrouter.models');
        return response()->json(['message' => 'Modèle créé avec succès', 'data' => $model], 201);
    }



    /**
     * Display the specified resource.
     */
    public function show() {}


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AiModel $model): JsonResponse
    {
        $validated = $request->validate(
            [
                'name' => 'required|string',
                'provider_name' => 'required|string|exists:provider_icons,name',
                'context_length' => 'nullable|integer',
                'max_completion_tokens' => 'nullable|integer',
                'pricing' => 'nullable|json',
                'is_active' => 'boolean',
            ]
        );

        $model->update($validated);
        cache()->forgot('openrouter.models');
        return response()->json(['message' => 'Modèle mis a jour', 'data' => $model], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Aimodel $model): JsonResponse
    {
        $model->delete();
        cache()->forget('openrouter.models');
        return response()->json(['message' => 'Modèle supprimé avec succès'], 200);
    }
}
