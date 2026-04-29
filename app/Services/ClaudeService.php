<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Wrapper de retrocompatibilidad. La logica real vive en AiProviderService,
 * que enruta entre Claude y OpenAI segun la configuracion del tenant.
 *
 * Se mantiene esta clase porque varios controladores la importan por nombre.
 */
final class ClaudeService
{
    private AiProviderService $provider;

    public function __construct(int $tenantId, ?int $agentId = null)
    {
        $this->provider = new AiProviderService($tenantId, $agentId);
    }

    public function call(string $feature, string|array $userMessage, int $maxTokens = 1024): array
    {
        return $this->provider->call($feature, $userMessage, $maxTokens, true);
    }

    public function summarizeConversation(string $transcript): array
    {
        return $this->provider->summarizeConversation($transcript);
    }

    public function detectIntent(string $message): array
    {
        return $this->provider->detectIntent($message);
    }

    public function suggestReply(string $transcript): array
    {
        return $this->provider->suggestReply($transcript);
    }

    public function evaluateSentiment(string $message): array
    {
        return $this->provider->evaluateSentiment($message);
    }

    public function autoReply(string $userMessage, string $history = ''): array
    {
        return $this->provider->autoReply($userMessage, $history);
    }

    public function scoreLead(string $contactInfo, string $conversationSummary = ''): array
    {
        return $this->provider->scoreLead($contactInfo, $conversationSummary);
    }

    public function generateCampaignMessage(string $audience, string $goal): array
    {
        return $this->provider->generateCampaignMessage($audience, $goal);
    }

    public function recommendNextAction(string $context): array
    {
        return $this->provider->recommendNextAction($context);
    }
}
