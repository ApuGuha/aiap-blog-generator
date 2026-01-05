<?php
if (!defined('ABSPATH')) exit;

class AI_Blog_Generator {

    private $api_key;

    public function __construct() {
        $this->api_key = trim(get_option('aibg_ai_api_key', ''));
    }

    public function generate_blog($topic) {

        if (empty($this->api_key)) {
            error_log('AI BLOG ERROR: API key is empty');
            return false;
        }

        if (empty($topic)) {
            error_log('AI BLOG ERROR: Topic is empty');
            return false;
        }

        return $this->call_openai($topic);
    }

    private function call_openai($topic) {

        error_log('AI BLOG: Starting OpenAI request for topic: ' . $topic);

        $endpoint = 'https://api.openai.com/v1/chat/completions';

        $prompt = "Generate a blog post about: {$topic}

            Return ONLY valid JSON (no markdown, no extra text) in this exact format:
            {
            \"title\": \"SEO-friendly title here\",
            \"content\": \"Full HTML blog content with paragraphs\",
            \"tags\": \"tag1,tag2,tag3,tag4,tag5\"
            }";

                    $payload = [
                        'model' => 'gpt-4o-mini',
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'You are a blog writer. Always respond with valid JSON only, no additional text or markdown.'
                            ],
                            [
                                'role' => 'user',
                                'content' => $prompt
                            ]
                        ],
                        'temperature' => 0.7
                    ];

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            error_log('AI BLOG WP ERROR: ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log('AI BLOG HTTP ERROR: Status code ' . $status_code);
            $body = wp_remote_retrieve_body($response);
            error_log('AI BLOG ERROR BODY: ' . $body);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        error_log('AI BLOG RAW RESPONSE: ' . substr($body, 0, 500));

        $data = json_decode($body, true);

        
        if (!isset($data['choices'][0]['message']['content'])) {
            error_log('AI BLOG INVALID STRUCTURE: ' . print_r($data, true));
            return false;
        }

        $raw = $data['choices'][0]['message']['content'];
        error_log('AI BLOG CONTENT LENGTH: ' . strlen($raw));

        
        $raw = preg_replace('/^```json\s*|\s*```$/s', '', trim($raw));
        
        
        if (preg_match('/<json>(.*?)<\/json>/s', $raw, $matches)) {
            $json_string = trim($matches[1]);
            error_log('AI BLOG: Extracted JSON from tags');
        } else {
            
            $json_string = trim($raw);
            error_log('AI BLOG: Using raw response as JSON');
        }

        $json = json_decode($json_string, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('AI BLOG JSON DECODE FAILED: ' . json_last_error_msg());
            error_log('AI BLOG JSON STRING: ' . $json_string);
            return false;
        }
        if (!isset($json['title']) || !isset($json['content']) || !isset($json['tags'])) {
            error_log('AI BLOG MISSING REQUIRED FIELDS: ' . print_r($json, true));
            return false;
        }
        $tags = $json['tags'];
        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        } elseif (!is_array($tags)) {
            $tags = [];
        }
        $tags = array_filter($tags, function($tag) {
            return !empty(trim($tag));
        });

        error_log('AI BLOG SUCCESS: Generated content with ' . count($tags) . ' tags');

        return [
            'title'   => sanitize_text_field($json['title']),
            'content' => wp_kses_post($json['content']),
            'tags'    => array_map('sanitize_text_field', $tags)
        ];
    }
}