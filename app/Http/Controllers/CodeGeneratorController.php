<?php
// app/Http/Controllers/CodeGeneratorController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CodeGeneratorController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:2000'
        ]);

        $prompt = $request->input('prompt');

        try {
            $claudeResponse = $this->callClaudeAPI($prompt);
            $codeData = $this->extractCode($claudeResponse);

            return response()->json([
                'success' => true,
                'data' => $codeData
            ]);
        } catch (\Exception $e) {
            Log::error('Code generation failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function callClaudeAPI($userPrompt)
    {
        $apiKey = env('ANTHROPIC_API_KEY');
        
        if (empty($apiKey)) {
            throw new \Exception('ANTHROPIC_API_KEY not set in .env file');
        }

        $systemPrompt = $this->getSystemPrompt();

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-sonnet-4-5-20250929',
            'max_tokens' => 4096,
            'system' => $systemPrompt,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $userPrompt
                ]
            ]
        ]);

        if (!$response->successful()) {
            throw new \Exception('API Error: ' . $response->body());
        }

        return $response->json();
    }

    private function getSystemPrompt()
    {
        return <<<'PROMPT'
You are a code generation assistant. Respond with ONLY a JSON object in this exact format:

{
  "type": "react",
  "code": "complete executable code here",
  "libraries": [],
  "description": "what this code does"
}

Rules:
1. ONLY output the JSON, nothing else
2. No markdown code blocks, no explanations
3. The "type" must be: "react", "html", "javascript", or "vue"
4. For React: Include complete component with ReactDOM.render() at the end
   Example: function App() { return <div>Hello</div>; } ReactDOM.render(<App />, document.getElementById('root'));
5. For HTML: Include full <!DOCTYPE html> structure
6. For JavaScript: Include executable vanilla JS
7. Escape quotes properly in JSON
8. Make the code functional and complete

Example React response:
{
  "type": "react",
  "code": "function App() { const [count, setCount] = React.useState(0); return <div style={{padding: '20px'}}><h1>Counter: {count}</h1><button onClick={() => setCount(count + 1)}>Increment</button></div>; } ReactDOM.render(<App />, document.getElementById('root'));",
  "libraries": [],
  "description": "A simple counter app"
}
PROMPT;
    }

    private function extractCode($claudeResponse)
    {
        $content = $claudeResponse['content'][0]['text'] ?? '';

        if (empty($content)) {
            throw new \Exception('Empty response from Claude API');
        }

        // Try direct JSON parse
        $decoded = json_decode($content, true);
        if ($decoded !== null && isset($decoded['code']) && isset($decoded['type'])) {
            return $this->validateCodeData($decoded);
        }

        // Try extracting from markdown JSON block
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
            if ($decoded !== null && isset($decoded['code'])) {
                return $this->validateCodeData($decoded);
            }
        }

        // Try extracting any JSON object
        if (preg_match('/\{[\s\S]*?"code"[\s\S]*?\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if ($decoded !== null && isset($decoded['code'])) {
                return $this->validateCodeData($decoded);
            }
        }

        // Last resort: extract code from any code block
        if (preg_match('/```(\w+)?\s*(.*?)\s*```/s', $content, $matches)) {
            return [
                'type' => $this->detectType($matches[1] ?? '', $matches[2]),
                'code' => $matches[2],
                'libraries' => [],
                'description' => 'Generated code'
            ];
        }

        throw new \Exception('Could not parse response. Raw: ' . substr($content, 0, 500));
    }

    private function validateCodeData($data)
    {
        return [
            'type' => $data['type'] ?? 'javascript',
            'code' => $data['code'] ?? '',
            'libraries' => $data['libraries'] ?? [],
            'description' => $data['description'] ?? 'Generated code'
        ];
    }

    private function detectType($lang, $code)
    {
        $lang = strtolower($lang);

        if ($lang === 'jsx' || strpos($code, 'React') !== false) {
            return 'react';
        }
        if ($lang === 'html' || strpos($code, '<!DOCTYPE') !== false) {
            return 'html';
        }
        if ($lang === 'vue' || strpos($code, 'Vue') !== false) {
            return 'vue';
        }

        return 'javascript';
    }
}