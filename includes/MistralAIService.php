<?php
/**
 * Service Mistral AI — complément aux suggestions locales (consultations, laboratoire).
 */
class MistralAIService
{
    private const API_URL = 'https://api.mistral.ai/v1/chat/completions';

    public function __construct()
    {
        require_once __DIR__ . '/PlatformAIConfig.php';
    }

    public static function getInstance(): self
    {
        return new self();
    }

    public function isActive(): bool
    {
        return PlatformAIConfig::get(PlatformAIConfig::KEY_ACTIVE, '0') === '1'
            && PlatformAIConfig::hasApiKey();
    }

    public function isEnabledForConsultations(): bool
    {
        return $this->isActive()
            && PlatformAIConfig::get(PlatformAIConfig::KEY_CONSULTATIONS, '1') === '1';
    }

    public function isEnabledForLaboratoire(): bool
    {
        return $this->isActive()
            && PlatformAIConfig::get(PlatformAIConfig::KEY_LABORATOIRE, '1') === '1';
    }

    public function getModel(): string
    {
        $model = trim(PlatformAIConfig::get(PlatformAIConfig::KEY_MODEL, 'mistral-small-latest'));
        return $model !== '' ? $model : 'mistral-small-latest';
    }

    /**
     * Enrichit les suggestions de diagnostic avec Mistral.
     *
     * @return array{enriched: bool, note?: string, error?: string}
     */
    public function enrichConsultationDiagnostic(
        array &$localSuggestions,
        string $symptomes,
        ?int $patientAge = null,
        ?string $patientSexe = null,
        ?string $antecedents = null,
        ?string $allergies = null
    ): array {
        if (!$this->isEnabledForConsultations()) {
            return ['enriched' => false];
        }

        $context = $this->buildPatientContext($patientAge, $patientSexe, $antecedents, $allergies);
        $localSummary = $this->summarizeLocalConsultation($localSuggestions);

        $systemPrompt = <<<'PROMPT'
Tu es un assistant médical pour médecins en Afrique de l'Ouest. Tu complètes des suggestions locales déjà générées.
Réponds UNIQUEMENT en JSON valide, sans markdown, avec cette structure exacte :
{"diagnostics":["..."],"traitements":["..."],"medicaments":["..."],"examens":["..."],"texte_diagnostic":"...","texte_traitement":"...","texte_ordonnance":"...","phrases":[{"cible":"diagnostic|traitement|ordonnance","texte":"..."}],"note":"..."}
Règles :
- diagnostics, traitements, medicaments, examens : phrases complètes rédigées en français (pas seulement 2-3 mots), style professionnel
- texte_diagnostic : paragraphe de 2 à 5 phrases, compte-rendu médical prêt à saisir
- texte_traitement : paragraphe décrivant le plan thérapeutique, surveillance et suivi
- texte_ordonnance : texte d'ordonnance rédigé (médicaments, posologies, durée, conseils)
- phrases : 1 à 4 suggestions supplémentaires sous forme de phrase entière, avec cible appropriée
- Ne pas répéter les suggestions locales déjà listées
- Le médecin valide ; formulations indicatives, pas de diagnostic définitif
- Tenir compte des allergies et antécédents si fournis
- Urgence potentielle : le mentionner dans "note"
PROMPT;

        $userPrompt = "Symptômes : {$symptomes}\n{$context}\nSuggestions locales existantes :\n{$localSummary}\n"
            . 'Propose des éléments complémentaires pertinents (JSON uniquement).';

        $parsed = $this->requestJson($systemPrompt, $userPrompt);
        if (isset($parsed['error'])) {
            return ['enriched' => false, 'error' => $parsed['error']];
        }

        $this->mergeConsultationLists($localSuggestions, $parsed);

        $mistralBlock = $localSuggestions['mistral'] ?? ['items' => []];
        $hasNewItems = $this->mistralHasContent($mistralBlock);

        return [
            'enriched' => $hasNewItems,
            'note' => $parsed['note'] ?? '',
            'has_textes' => !empty($mistralBlock['textes']) || !empty($mistralBlock['phrases']),
        ];
    }

    /**
     * Enrichit les suggestions laboratoire avec Mistral.
     *
     * @return array{enriched: bool, note?: string, error?: string}
     */
    public function enrichLaboratoireSuggestions(
        array &$data,
        string $typeAnalyse,
        string $typeTitle,
        ?int $patientAge = null,
        ?string $patientSexe = null,
        ?string $description = null
    ): array {
        if (!$this->isEnabledForLaboratoire()) {
            return ['enriched' => false];
        }

        $context = $this->buildPatientContext($patientAge, $patientSexe, null, null);
        $existing = implode(', ', array_slice($data['suggestions'] ?? [], 0, 12));

        $systemPrompt = <<<'PROMPT'
Tu es un biologiste médical assistant pour médecins. Complète des suggestions d'analyses déjà proposées.
Réponds UNIQUEMENT en JSON valide, sans markdown :
{"suggestions":["..."],"indications":["..."],"texte_description":"...","texte_instructions":"...","phrases":[{"cible":"description|instructions","texte":"..."}],"preparation":"...","note":"..."}
Règles :
- suggestions et indications : phrases complètes rédigées en français (pas seulement un nom d'examen)
- texte_description : paragraphe rédigé décrivant l'analyse demandée, son objectif et le contexte clinique
- texte_instructions : paragraphe rédigé d'instructions patient (préparation, délai, précautions)
- phrases : 1 à 3 phrases supplémentaires avec cible "description" ou "instructions"
- Ne pas répéter les analyses déjà listées
- "preparation" : complément si utile, sinon chaîne vide
PROMPT;

        $extra = $description ? "\nContexte clinique : {$description}" : '';
        $userPrompt = "Type d'analyse : {$typeTitle} ({$typeAnalyse})\n{$context}{$extra}\n"
            . "Analyses déjà suggérées : {$existing}\nPropose des compléments (JSON uniquement).";

        $parsed = $this->requestJson($systemPrompt, $userPrompt);
        if (isset($parsed['error'])) {
            return ['enriched' => false, 'error' => $parsed['error']];
        }

        $data['mistral_suggestions'] = [];
        foreach ($parsed['suggestions'] ?? [] as $item) {
            $item = trim((string) $item);
            if ($item === '' || $this->listContains($data['suggestions'] ?? [], $item)) {
                continue;
            }
            $data['suggestions'][] = $item;
            $data['mistral_suggestions'][] = $item;
        }

        foreach ($parsed['indications'] ?? [] as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            if (!isset($data['indications'])) {
                $data['indications'] = [];
            }
            if (!$this->listContains($data['indications'], $item)) {
                $data['indications'][] = $item;
            }
        }

        if (!empty($parsed['preparation']) && empty($data['mistral_preparation'])) {
            $prep = trim((string) $parsed['preparation']);
            if ($prep !== '' && stripos($data['preparation'] ?? '', $prep) === false) {
                $data['preparation'] = trim(($data['preparation'] ?? '') . ' ' . $prep);
                $data['mistral_preparation'] = true;
            }
        }

        $data['mistral_textes'] = [];
        foreach (['description' => 'texte_description', 'instructions' => 'texte_instructions'] as $key => $parsedKey) {
            $text = trim((string) ($parsed[$parsedKey] ?? ''));
            if ($text !== '') {
                $data['mistral_textes'][$key] = $text;
            }
        }

        $data['mistral_phrases'] = [];
        foreach ($parsed['phrases'] ?? [] as $phrase) {
            if (!is_array($phrase)) {
                continue;
            }
            $text = trim((string) ($phrase['texte'] ?? ''));
            $cible = trim((string) ($phrase['cible'] ?? ''));
            if ($text !== '' && in_array($cible, ['description', 'instructions'], true)) {
                $data['mistral_phrases'][] = ['cible' => $cible, 'texte' => $text];
            }
        }

        $hasText = !empty($data['mistral_textes']) || !empty($data['mistral_phrases']);

        return [
            'enriched' => !empty($data['mistral_suggestions']) || $hasText,
            'note' => $parsed['note'] ?? '',
            'has_textes' => $hasText,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPublicConfig(): array
    {
        return [
            'mistral_active' => $this->isActive(),
            'consultations' => $this->isEnabledForConsultations(),
            'laboratoire' => $this->isEnabledForLaboratoire(),
            'model' => $this->getModel(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestJson(string $systemPrompt, string $userPrompt): array
    {
        $apiKey = trim(PlatformAIConfig::get(PlatformAIConfig::KEY_API, ''));
        if ($apiKey === '') {
            return ['error' => 'Clé API Mistral non configurée'];
        }

        $payload = [
            'model' => $this->getModel(),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.3,
            'max_tokens' => 1024,
            'response_format' => ['type' => 'json_object'],
        ];

        $response = $this->httpPost(self::API_URL, $payload, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        if (isset($response['error'])) {
            error_log('MistralAIService: ' . $response['error']);
            return ['error' => $response['error']];
        }

        $content = $response['choices'][0]['message']['content'] ?? '';
        if ($content === '') {
            return ['error' => 'Réponse Mistral vide'];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            $decoded = json_decode($this->extractJson($content), true);
        }
        if (!is_array($decoded)) {
            return ['error' => 'JSON Mistral invalide'];
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $headers
     * @return array<string, mixed>
     */
    private function httpPost(string $url, array $payload, array $headers): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $timeout = (int) PlatformAIConfig::get(PlatformAIConfig::KEY_TIMEOUT, '25');
        if ($timeout < 5) {
            $timeout = 5;
        }
        if ($timeout > 60) {
            $timeout = 60;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);
            $raw = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($raw === false) {
                return ['error' => 'Connexion Mistral échouée : ' . $curlError];
            }
            $data = json_decode($raw, true);
            if ($httpCode >= 400) {
                $msg = is_array($data) ? ($data['message'] ?? $data['error'] ?? $raw) : $raw;
                return ['error' => 'Mistral HTTP ' . $httpCode . ' : ' . $msg];
            }
            return is_array($data) ? $data : ['error' => 'Réponse Mistral illisible'];
        }

        $headerLines = implode("\r\n", $headers);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headerLines . "\r\n",
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            return ['error' => 'Connexion Mistral impossible (curl requis ou allow_url_fopen)'];
        }
        $httpCode = 200;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $httpCode = (int) $m[1];
        }
        $data = json_decode($raw, true);
        if ($httpCode >= 400) {
            $msg = is_array($data) ? ($data['message'] ?? $data['error'] ?? $raw) : $raw;
            return ['error' => 'Mistral HTTP ' . $httpCode . ' : ' . $msg];
        }
        return is_array($data) ? $data : ['error' => 'Réponse Mistral illisible'];
    }

    private function extractJson(string $text): string
    {
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            return $m[0];
        }
        return $text;
    }

    private function buildPatientContext(
        ?int $age,
        ?string $sexe,
        ?string $antecedents,
        ?string $allergies
    ): string {
        $parts = [];
        if ($age !== null) {
            $parts[] = "Âge : {$age} ans";
        }
        if ($sexe) {
            $parts[] = 'Sexe : ' . ($sexe === 'M' ? 'Masculin' : 'Féminin');
        }
        if ($antecedents) {
            $parts[] = 'Antécédents : ' . $antecedents;
        }
        if ($allergies) {
            $parts[] = 'Allergies : ' . $allergies;
        }
        return $parts ? implode("\n", $parts) : 'Patient : informations démographiques non renseignées';
    }

    private function summarizeLocalConsultation(array $local): string
    {
        $analysis = $local['diagnostic']['analysis'] ?? [];
        $lines = [];
        foreach (['diagnostics', 'traitements', 'medicaments', 'examens'] as $key) {
            if (!empty($analysis[$key])) {
                $lines[] = ucfirst($key) . ' : ' . implode('; ', array_slice($analysis[$key], 0, 6));
            }
        }
        return $lines ? implode("\n", $lines) : 'Aucune suggestion locale';
    }

    /**
     * @param array<string, mixed> $localSuggestions
     * @param array<string, mixed> $parsed
     */
    private function mergeConsultationLists(array &$localSuggestions, array $parsed): void
    {
        if (!isset($localSuggestions['diagnostic']['analysis'])) {
            return;
        }

        $analysis = &$localSuggestions['diagnostic']['analysis'];
        $mistralItems = [
            'diagnostics' => [],
            'traitements' => [],
            'medicaments' => [],
            'examens' => [],
        ];

        foreach ($mistralItems as $key => $_) {
            $targetKey = $key;
            foreach ($parsed[$key] ?? [] as $item) {
                $item = trim((string) $item);
                if ($item === '') {
                    continue;
                }
                if (!$this->listContains($analysis[$targetKey] ?? [], $item)) {
                    if (!isset($analysis[$targetKey])) {
                        $analysis[$targetKey] = [];
                    }
                    $analysis[$targetKey][] = $item;
                    $mistralItems[$key][] = $item;
                }
            }
        }

        $localSuggestions['mistral'] = [
            'items' => $mistralItems,
            'textes' => $this->extractConsultationTextes($parsed),
            'phrases' => $this->extractPhrases($parsed['phrases'] ?? [], ['diagnostic', 'traitement', 'ordonnance']),
            'note' => $parsed['note'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $parsed
     * @return array<string, string>
     */
    private function extractConsultationTextes(array $parsed): array
    {
        $textes = [];
        $map = [
            'diagnostic' => 'texte_diagnostic',
            'traitement' => 'texte_traitement',
            'ordonnance' => 'texte_ordonnance',
        ];
        foreach ($map as $key => $parsedKey) {
            $text = trim((string) ($parsed[$parsedKey] ?? ''));
            if ($text !== '') {
                $textes[$key] = $text;
            }
        }
        return $textes;
    }

    /**
     * @param array<int, mixed> $phrases
     * @param array<int, string> $allowedCibles
     * @return array<int, array{cible: string, texte: string}>
     */
    private function extractPhrases(array $phrases, array $allowedCibles): array
    {
        $result = [];
        foreach ($phrases as $phrase) {
            if (!is_array($phrase)) {
                continue;
            }
            $text = trim((string) ($phrase['texte'] ?? ''));
            $cible = trim((string) ($phrase['cible'] ?? ''));
            if ($text !== '' && in_array($cible, $allowedCibles, true)) {
                $result[] = ['cible' => $cible, 'texte' => $text];
            }
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $mistralBlock
     */
    private function mistralHasContent(array $mistralBlock): bool
    {
        foreach ($mistralBlock['items'] ?? [] as $items) {
            if (!empty($items)) {
                return true;
            }
        }
        if (!empty($mistralBlock['textes']) || !empty($mistralBlock['phrases'])) {
            return true;
        }
        return false;
    }

    /**
     * @param array<int, string> $list
     */
    private function listContains(array $list, string $needle): bool
    {
        $n = function_exists('mb_strtolower')
            ? mb_strtolower(trim($needle))
            : strtolower(trim($needle));
        foreach ($list as $item) {
            $hay = function_exists('mb_strtolower')
                ? mb_strtolower(trim((string) $item))
                : strtolower(trim((string) $item));
            if ($hay === $n) {
                return true;
            }
        }
        return false;
    }
}
