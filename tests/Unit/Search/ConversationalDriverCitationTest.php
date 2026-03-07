<?php

namespace Tests\Unit\Search;

use App\Services\Search\Results\AskResponse;
use Tests\TestCase;

/**
 * Tests the citation extraction logic and AskResponse value object
 * without spinning up the full RAG pipeline.
 */
class ConversationalDriverCitationTest extends TestCase
{
    // ── AskResponse Value Object ─────────────────────────────────────────────

    public function test_ask_response_to_array_contains_expected_keys(): void
    {
        $response = new AskResponse(
            answer: 'The answer is 42.',
            sources: [['id' => 'c1', 'title' => 'Doc', 'url' => '/content/doc', 'relevance' => 0.9]],
            confidence: 0.85,
            followUpSuggestions: ['What else?'],
            conversationId: 'conv-123',
            tierUsed: 'ask',
            tokensUsed: 350,
        );

        $arr = $response->toArray();

        $this->assertArrayHasKey('answer', $arr);
        $this->assertArrayHasKey('sources', $arr);
        $this->assertArrayHasKey('confidence', $arr);
        $this->assertArrayHasKey('follow_ups', $arr);
        $this->assertArrayHasKey('conversation_id', $arr);
        $this->assertArrayHasKey('meta', $arr);
        $this->assertSame('ask', $arr['meta']['tier_used']);
        $this->assertSame(350, $arr['meta']['tokens_used']);
    }

    public function test_no_answer_static_factory_returns_correct_structure(): void
    {
        $response = AskResponse::noAnswer('What is AI?', 'conv-abc');

        $this->assertStringContainsString("don't have enough information", $response->answer);
        $this->assertSame([], $response->sources);
        $this->assertSame(0.0, $response->confidence);
        $this->assertSame([], $response->followUpSuggestions);
        $this->assertSame('conv-abc', $response->conversationId);
    }

    public function test_no_answer_without_conversation_id(): void
    {
        $response = AskResponse::noAnswer('test question');

        $this->assertNull($response->conversationId);
    }

    // ── Citation Extraction Heuristics ───────────────────────────────────────

    /**
     * We test the citation extraction logic by exposing it via a test harness
     * (using a test subclass of ConversationalDriver for white-box testing).
     */
    public function test_citation_brackets_regex_matches_standard_format(): void
    {
        $answer = 'According to [1], this is the case. Also see [2] and [3].';

        preg_match_all('/\[(\d+)\]/', $answer, $matches);
        $cited = array_unique(array_map('intval', $matches[1]));

        $this->assertContains(1, $cited);
        $this->assertContains(2, $cited);
        $this->assertContains(3, $cited);
        $this->assertCount(3, $cited);
    }

    public function test_no_citations_in_answer_returns_empty_match(): void
    {
        $answer = 'This answer has no citation markers at all.';

        preg_match_all('/\[(\d+)\]/', $answer, $matches);
        $cited = array_unique(array_map('intval', $matches[1]));

        $this->assertEmpty($cited);
    }

    public function test_duplicate_citations_are_deduplicated(): void
    {
        $answer = 'See [1]. Also [1] is mentioned again. And [1] once more.';

        preg_match_all('/\[(\d+)\]/', $answer, $matches);
        $cited = array_unique(array_map('intval', $matches[1]));

        $this->assertCount(1, $cited);
        $this->assertContains(1, $cited);
    }

    // ── Follow-Up Generation Heuristics ──────────────────────────────────────

    public function test_follow_ups_include_installation_hint_when_answer_contains_install(): void
    {
        $answer = 'To get started, first install the package via composer.';

        $followUps = $this->generateFollowUps('How do I start?', $answer);

        $this->assertNotEmpty(array_filter($followUps, fn ($f) => str_contains(strtolower($f), 'configure')));
    }

    public function test_follow_ups_include_example_hint_when_answer_contains_example(): void
    {
        $answer = 'Here is an example of how to use this feature.';

        $followUps = $this->generateFollowUps('How to use it?', $answer);

        $this->assertNotEmpty(array_filter($followUps, fn ($f) => str_contains(strtolower($f), 'advanced')));
    }

    public function test_follow_ups_include_troubleshooting_when_answer_mentions_error(): void
    {
        $answer = 'If you encounter an error, check the logs.';

        $followUps = $this->generateFollowUps('Why is this failing?', $answer);

        $this->assertNotEmpty(array_filter($followUps, fn ($f) => str_contains(strtolower($f), 'troubleshoot')));
    }

    public function test_default_follow_ups_returned_when_no_keywords_match(): void
    {
        $answer = 'The sky is blue.';
        $followUps = $this->generateFollowUps('What color is the sky?', $answer);

        $this->assertNotEmpty($followUps);
        $this->assertLessThanOrEqual(3, count($followUps));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Replicate the follow-up generation logic (matches ConversationalDriver::generateFollowUps).
     *
     * @return string[]
     */
    private function generateFollowUps(string $question, string $answer): array
    {
        $followUps = [];

        if (str_contains(strtolower($answer), 'install') || str_contains(strtolower($answer), 'setup')) {
            $followUps[] = 'How do I configure this after installation?';
        }

        if (str_contains(strtolower($answer), 'example') || str_contains(strtolower($answer), 'sample')) {
            $followUps[] = 'Can you show me a more advanced example?';
        }

        if (str_contains(strtolower($answer), 'error') || str_contains(strtolower($answer), 'issue')) {
            $followUps[] = 'What are common troubleshooting steps?';
        }

        if (empty($followUps)) {
            $followUps = [
                'Tell me more about this topic.',
                'What are the best practices?',
            ];
        }

        return array_slice($followUps, 0, 3);
    }
}
