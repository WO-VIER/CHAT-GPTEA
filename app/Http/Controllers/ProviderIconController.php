<?php

namespace App\Http\Controllers;

use App\Models\ProviderIcon;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use NunoMaduro\Collision\Provider;

class ProviderIconController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() {}

    public function list(): JsonResponse
    {
        $icons = ProviderIcon::orderBy('name')->get();
        return response()->json([
            'data' => $icons,
            'count' => $icons->count()
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['name' => 'required|string|unique:provider_icons', 'url' => 'required|url']);
        $table = ProviderIcon::create($validated);
        return response()->json(['message' => 'Table populer', 'data' => $table], 201); // 201 Pour création
    }

    /**
     * Display the specified resource.
     */
    public function show(ProviderIcon $providerIcon)
    {
        return response()->json(['data' => $providerIcon]);
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
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function populate(): JsonResponse
    {
        try {
            //Si en local
            //$jsonpath = base_path('all-providers.json');
            $reponse = Http::get('https://openrouter.ai/api/frontend/all-providers');

            if (!$reponse->successful()) {
                logger()->warning('Erreur lors de la recup des models icons', ['status' => $reponse->status(), 'body' => $reponse->body()]);
                return response()->json(['message' => 'Erreur lors de la récupération des modèles icons'], $reponse->status());
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

            ProviderIcon::updateOrCreate(
                ['name' => 'Huggingface'],
                ['url' => 'https://t0.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&url=https://huggingface.co/&size=256']
            );

            foreach ($json['data'] as $provider) {
                if (isset($provider['name']) && isset($provider['icon']['url'])) {
                    $iconUrl = $provider['icon']['url'];
                    if (str_starts_with($iconUrl, '/images/icons/')) {
                        $iconUrl = 'https://openrouter.ai' . $iconUrl;
                    }
                    ProviderIcon::updateOrCreate(
                        ['name' => $provider['name']],
                        ['url' => $iconUrl]
                    );
                    $i++;
                }
            }
            return response()->json(['message' => $i . 'Icons de provider populer dans la table provider_icons'], 201);
        } catch (\Exception $e) {
            logger()->error(
                "Erreur lors de la recupération de icons",
                [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return response()->json(['message' => 'Erreur ' . $e->getMessage()], 500);
        }
    }
}
