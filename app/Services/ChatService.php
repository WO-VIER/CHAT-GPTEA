<?php

namespace App\Services;

use App\Models\ProviderIcon;
use Illuminate\Support\Facades\Http;
use App\Models\AiModel;
use App\Models\Message;
use Debugbar;
use GuzzleHttp\Promise\Create;
use OpenAI\Responses\Chat\CreateResponseMessage;
use phpDocumentor\Reflection\PseudoTypes\LowercaseString;
use PHPUnit\Framework\Constraint\IsEmpty;

use function Pest\Laravel\json;

class ChatService
{
    private $baseUrl;
    private $apiKey;
    private $client;
    public const DEFAULT_MODEL = 'openai/gpt-4.1-mini';
    public string $lasMessage = '';
    public string $lastModel = self::DEFAULT_MODEL;

    public function __construct()
    {
        $this->baseUrl = config('services.openrouter.base_url', 'https://openrouter.ai/api/v1');
        $this->apiKey = config('services.openrouter.api_key');
        $this->client = $this->createOpenAIClient();
    }

    /**
     * @return array<array-key, array{
     *     id: string,
     *     name: string,
     *     context_length: int,
     *     max_completion_tokens: int,
     *     pricing: array{prompt: int, completion: int}
     * }>
     */
    public function getModels(): array
    {
        return cache()->remember('openai.models', now()->addHour(), function () {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get($this->baseUrl . '/models');
            $json = $response->json();

            if (!isset($json['data']) || !is_array($json['data'])) {
                logger()->error('Erreur lors de la recup des models', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            return collect($json['data'])
                ->sortBy('name')
                ->map(function ($model) {
                    return [
                        'id' => $model['id'],
                        'name' => $model['name'],
                        'context_length' => $model['context_length'],
                        'max_completion_tokens' => $model['top_provider']['max_completion_tokens'],
                        'pricing' => $model['pricing'],
                    ];
                })
                ->values()
                ->all()
            ;
        });
    }

    /**
     * @param array{role: 'user'|'assistant'|'system'|'function', content: string} $messages
     * @param string|null $model
     * @param float $temperature
     *
     * @return string
     */

    public function parseMessage(string $message): array
    {
        $result = [
            'commands' => [],
            'cleanMessage' => $message,
            'hasCommands' => false,
            'hasContext' => false,
        ];

        $pattern = '/\{\/(\w+)\}/';
        preg_match_all($pattern, $message, $matches);

        if (!empty($matches[1])) {
            $tokens = array_unique($matches[1]);

            foreach ($tokens as $token) {
                $lowToken = strtolower($token);
                if ($lowToken === 'contexte' || $lowToken === 'context') {
                    $result['hasContext'] = true;
                } else {
                    $result['commands'][] = '/' . $lowToken;
                    $result['hasCommands'] = true;
                }
            }
        }

        $result['cleanMessage'] = preg_replace($pattern, '', $message);
        $result['cleanMessage'] = trim($result['cleanMessage']);
        return $result;
    }

    /**
     * Version streaming qui retourne un StreamResponse pour les SSE.
     *
     * @param array{role: 'assistant'|'function'|'system'|'user', content: string} $messages
     */
    public function sendMessageStream(array $messages, ?string $model = null, float $temperature = 0.7)
    {
        try {
            logger()->info('Envoi du message', [
                'model' => $model,
                'temperature' => $temperature,
            ]);

            $models = collect($this->getModels());
            if (!$model || !$models->contains('id', $model)) {
                $model = self::DEFAULT_MODEL;
                logger()->info('Modèle par défaut utilisé:', ['model' => $model]);
            }

            $lastMessage = $messages[count($messages) - 1]['content']; //Message de l'utilisateur vers ia
            $parsedMessage = $this->parseMessage($lastMessage); //Parse le dernier message
            //dd($parsedMessage);
            if (!empty($parsedMessage['cleanMessage']))
                $messages[count($messages) - 1]['content'] = $parsedMessage['cleanMessage'];
            $messages = [$this->getChatSystemPrompt($parsedMessage), ...$messages];
            //dd($messages);
            //$messages = [$this->getChatSystemPrompt($parsedMessage['hasCommands'],$parsedMessage['commands']), ...$parsedMessage['cleanMessage']];

            //dd($messages);
            //$messages = [$this->getChatSystemPrompt($parsedMessage['hasCommands'],$parsedMessage['commands']), ...$parsedMessage['cleanMessage']];
            //dd($messages);
            $stream = $this->client->chat()->createStreamed([
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'stream' => true,
            ]);
            return $stream;
        } catch (\Exception $e) {
            logger()->error('Erreur dans sendMessageStream', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function sendMessage(array $messages, string $model = null, float $temperature = 0.7): string
    {
        try {
            logger()->info('Envoi du message', [
                'model' => $model,
                'temperature' => $temperature,
            ]);

            $models = collect($this->getModels());
            if (!$model || !$models->contains('id', $model)) {
                $model = self::DEFAULT_MODEL;
                logger()->info('Modèle par défaut utilisé:', ['model' => $model]);
            }

            $lastMessage = $messages[count($messages) - 1]['content']; //Message de l'utilisateur vers ia
            $parsedMessage = $this->parseMessage($lastMessage); //Parse le dernier message
            //dd($parsedMessage);
            if (!empty($parsedMessage['cleanMessage']))
                $messages[count($messages) - 1]['content'] = $parsedMessage['cleanMessage'];
            $messages = [$this->getChatSystemPrompt($parsedMessage), ...$messages];
            //dd($messages);
            //$messages = [$this->getChatSystemPrompt($parsedMessage['hasCommands'],$parsedMessage['commands']), ...$parsedMessage['cleanMessage']];
            //dd($messages);
            $response = $this->client->chat()->create([
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
            ]);

            logger()->info('Réponse reçue:', ['response' => $response]);

            $content = $response->choices[0]->message->content;
            $this->lasMessage = $content;
            $this->lastModel = $model;
            return $content;
        } catch (\Exception $e) {
            logger()->error('Erreur dans sendMessage:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function createOpenAIClient(): \OpenAI\Client
    {
        return \OpenAI::factory()
            ->withApiKey($this->apiKey)
            ->withBaseUri($this->baseUrl)
            ->make()
        ;
    }

    /**
     * @return array{role: 'system', content: string}
     */
    private function getChatSystemPrompt(array $parsedMessage): array
    {
        $user = auth()->user();
        $now = now()->locale('fr')->format('l d F Y H:i');

        $systemContext = "Tu es un assistant de chat. La date et l'heure actuelle est le {$now}.\n Tu es actuellement utilisé par {$user->name}.\n";

        if ($user->user_context && $parsedMessage['hasContext'])
            $systemContext .= "Contexte utilisateur: \n{$user->user_context}\n";
        if ($user->ai_behavior && $parsedMessage['hasContext'])
            $systemContext .= "Comportemement de l'IA: \n{$user->ai_behavior}\n";

        if ($parsedMessage['hasCommands'] && !empty($parsedMessage['commands']) && $user->custom_commands) {
            $systemContext .= "Commandes personnalisées:\n";
            foreach ($user->custom_commands as $command) {
                if (in_array(strtolower($command['token']), $parsedMessage['commands']))
                    $systemContext .= "- {$command['token']} : {$command['description']}\n";
            }
            $systemContext .= "\n";
        }
        return ['role' => 'system', 'content' => $systemContext];
    }

    public function getModelsIcons(): array
    {
        return cache()->remember('openrouter.provider_icons', now()->addDay(), function () {
            try {
                $reponse = Http::get('https://openrouter.ai/api/frontend/all-providers');
                /*
				$reponse = Http::withHeaders([
					'Authorization' => 'Bearer ' . $this->apiKey,
				])->get('https://openrouter.ai/api/frontend/all-providers');
				*/
                if (!$reponse->successful()) {
                    logger()->warning('Erreur lors de la recup des icons', ['status' => $reponse->status(), 'body' => $reponse->body()]);
                    return [];
                }

                $jsonProviders = $reponse->json();
                $modelsIcons = [];

                foreach ($jsonProviders['data'] as $provider) {
                    if (isset($provider['name']) && isset($provider['icon']['url'])) {
                        $iconUrl = $provider['icon']['url'];

                        if (str_starts_with($iconUrl, '/images/icons/')) {
                            //https://t0.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&url=https://www.alibabacloud.com/&size=256
                            //or
                            ///images/icons/Bedrock.svg
                            $iconUrl = 'https://openrouter.ai' . $iconUrl;
                        }
                        $modelsIcons[$provider['name']] = $iconUrl;

                        logger()->info('Icon récupérée', [
                            'name' => $provider['name'],
                            'icon_url' => $iconUrl,
                        ]);
                    }
                }

                return $modelsIcons;
            } catch (\Exception $e) {
                logger()->error(
                    "Erreur lors de la recupération de icons",
                    [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]
                );

                return [];
            }
        });
    }

    public function getModelsIconsDb(): array
    {
        return cache()->remember('openrouter.provider_icons_db', now()->addDay(), function () {
            return ProviderIcon::pluck('url', 'name')->toArray();
        });
    }

    public function populateModelsIcons(): void
    {
        try {
            $reponse = Http::get('https://openrouter.ai/api/frontend/all-providers');
            /*
				$reponse = Http::withHeaders([
					'Authorization' => 'Bearer ' . $this->apiKey,
				])->get('https://openrouter.ai/api/frontend/all-providers');
				*/
            if (!$reponse->successful()) {
                logger()->warning('Erreur lors de la recup des icons', ['status' => $reponse->status(), 'body' => $reponse->body()]);
                return;
            }

            $jsonProviders = $reponse->json();

            foreach ($jsonProviders['data'] as $provider) {
                if (isset($provider['name']) && isset($provider['icon']['url'])) {
                    $iconUrl = $provider['icon']['url'];
                    if (str_starts_with($iconUrl, '/images/icons/')) {
                        $iconUrl = 'https://openrouter.ai' . $iconUrl;
                    }
                    ProviderIcon::updateOrCreate(['name' => $provider['name']], ['url' => $iconUrl]);
                    logger()->info('Icon populer', [
                        'name' => $provider['name'],
                        'icon_url' => $iconUrl,
                    ]);
                }
            }
        } catch (\Exception $e) {
            logger()->error(
                "Erreur lors de la recupération de icons",
                [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
        }
    }

    public function getModelsFromDb(): array
    {
        return cache()->remember('oppenrouter.models_db', now()->addDay(), function () {
            return AiModel::where('is_active', true)->with('providerIcon')->orderBy('name')->get()
                ->map(function ($model) {
                    return
                        [
                            'id' => $model->model_id,
                            'name' => $model->name,
                            'provider_name' => $model->provider_name,
                            'provider_icon' => $model->providerIcon->url,
                            'context_length' => $model->context_length,
                            'max_completion_tokens' => $model->max_completion_tokens,
                            'pricing' => $model->pricing,
                        ];
                })->toArray();
        });
    }

    public function getModelsWithIcons(): array
    {
        return cache()->remember('openrouter.models_with_icons', now()->addDay(), function () {
            $apiModels = $this->getModels();
            $dbModels = AiModel::where('is_active', true)->with('providerIcon')->get()->keyBy('model_id');
            //dd($dbModels);

            return collect($apiModels)->map(function ($apiModel) use ($dbModels) {
                $dbModelId = $dbModels->get($apiModel['id']);
                if ($dbModelId && $dbModelId->providerIcon) {
                    $apiModel['privider_name'] = $dbModelId->provider_name;
                    $apiModel['provider_icon'] = $dbModelId->providerIcon->url;
                } else {
                    $apiModel['provider_icon'] = null;
                }
                return $apiModel;
            })->values()->toArray();
        });
    }
}
