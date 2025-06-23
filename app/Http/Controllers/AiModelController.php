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

    public function list() : JsonResponse
    {
       $models = AiModel::with('providerIcon')->orderBy('name')->get();
       return response()->json(
        [
            'data'=> $models,
            'count' => $models->count()
        ]);
    }

    public function populateNotAuth() : JsonResponse
    {

        try
			{
				//$reponse = Http::get('https://openrouter.ai/api/frontend/all-providers');

				$reponse = Http::get('https://openrouter.ai/api/frontend/models');

				if (!$reponse->successful())
				{
					logger()->warning('Erreur lors de la recup des models', ['status' => $reponse->status(), 'body' => $reponse->body()]);
					return response()->json(['message' => 'Erreur lors de la récupération des modèles'], $reponse->status());
				}

				$json = $reponse->json();
                if(!isset($json['data']) || !is_array($json['data']))
                {
                    logger()->error('Erreur lors de la recup des models', [
                    'status' => $reponse->status(),
                    'body' => $reponse->body(),]);
                    return response()->json(['message' => 'Format de données incorrect'], 500);
                }

                $i = 0;
                foreach($json['data'] as $model)
                {
                    $providerName = null;
                    if (isset($model['endpoint']['provider_name']))
                    {
                        $providerName = $model['endpoint']['provider_name'];
                    }else
                    {
                        $split = explode('/',$model['slug']);
                        $providerName = $split[0];
                    }
                    if(empty($providerName))
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
			}
			catch (\Exception $e)
			{
				logger()->error("Erreur lors de la population de AiModels",
				[
					'message' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				]);

                return response()->json(['message' => 'Erreur lors de la population des modèles: ' . $e->getMessage()], 500);
			}
    }

    public function populate() : JsonResponse
    {
        try
			{
				$reponse = Http::withHeaders([
                    'Authorization' => 'Bearer ' . config('services.openrouter.api_key'),
                    ])->get(config('services.openrouter.base_url') . '/models');

				if (!$reponse->successful())
				{
					logger()->warning('Erreur lors de la recup des models', ['status' => $reponse->status(), 'body' => $reponse->body()]);
					return response()->json(['message' => 'Erreur lors de la récupération des modèles'], $reponse->status());
				}

				$json = $reponse->json();
                if(!isset($json['data']) || !is_array($json['data']))
                {
                    logger()->error('Erreur lors de la recup des models', [
                    'status' => $reponse->status(),
                    'body' => $reponse->body(),]);
                    return response()->json(['message' => 'Format de données incorrect'], 500);
                }

                $i = 0;
                foreach($json['data'] as $model)
                {
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
			}
			catch (\Exception $e)
			{
				logger()->error("Erreur lors de la population de AiModels",
				[
					'message' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				]);

                return response()->json(['message' => 'Erreur lors de la population des modèles: ' . $e->getMessage()], 500);
			}
    }

    public function nullproviderIcons() : JsonResponse
    {
        try
        {
            $modelsWithoutIcons = AiModel::whereDoesntHave('providerIcon')->get();
            // $models = AiModel::with('providerIcon')->orderBy('name')->get();

            $i = 0;
            foreach($modelsWithoutIcons as $model)
            {
                $model->update(['provider_name' => 'Huggingface']);
                $i++;
            }

            cache()->forget('openrouter.models');

            return response()->json(['message' => $i . 'models a provider_name NULL mis a Huggingface'],200);

        }
        catch(\Exception $e)
        {
            logger()->error("Erreur lors de l'update de la db AiModels",
				[
					'message' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				]);

                return response()->json(['message' => "Erreur lors de l'update de la db AiModels " . $e->getMessage()], 500);
        }
    }


    public function store(Request $request) : JsonResponse
    {
       $validated = $request->validate(
        [
            'model_id' => 'required|string|unique:ai_models',
            'name'=> 'required|string',
            'provider_name' => 'required|string|exists:provider_icons,name',
            'context_length' => 'nullable|integer',
            'max_completion_tokens' => 'nullable|integer',
            'pricing' => 'nullable|json',
            'is_active' => 'boolean',
        ]);

        $model = AiModel::create($validated);
        cache()->forget('openrouter.models');
        return response()->json(['message' => 'Modèle créé avec succès', 'data' => $model], 201);
    }



    /**
     * Display the specified resource.
     */
    public function show()
    {

    }


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
    public function update(Request $request, AiModel $model) : JsonResponse
    {
        $validated = $request->validate(
        [
            'name'=> 'required|string',
            'provider_name' => 'required|string|exists:provider_icons,name',
            'context_length' => 'nullable|integer',
            'max_completion_tokens' => 'nullable|integer',
            'pricing' => 'nullable|json',
            'is_active' => 'boolean',
        ]);

        $model->update($validated);
        cache()->forgot('openrouter.models');
        return response()->json(['message' => 'Modèle mis a jour', 'data' => $model], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Aimodel $model) : JsonResponse
    {
        $model->delete();
        cache()->forget('openrouter.models');
        return response()->json(['message' => 'Modèle supprimé avec succès'], 200);
    }
}
