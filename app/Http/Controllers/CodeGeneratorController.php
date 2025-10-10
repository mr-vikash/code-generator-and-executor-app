<?php
// app/Http/Controllers/CodeGeneratorController.php

namespace App\Http\Controllers;

use App\Models\CodeHistory;
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

            // Save to history
            $history = CodeHistory::create([
                'prompt' => $prompt,
                'code' => $codeData['code'],
                'type' => $codeData['type'],
                'description' => $codeData['description'],
                'libraries' => json_encode($codeData['libraries']),
            ]);

            return response()->json([
                'success' => true,
                'data' => array_merge($codeData, ['id' => $history->id])
            ]);
        } catch (\Exception $e) {
            Log::error('Code generation failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function generateStream(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:2000'
        ]);

        $prompt = $request->input('prompt');

        return response()->stream(function () use ($prompt) {
            try {
                $apiKey = env('ANTHROPIC_API_KEY');
                
                if (empty($apiKey)) {
                    echo "data: " . json_encode(['error' => 'ANTHROPIC_API_KEY not set']) . "\n\n";
                    return;
                }

                $systemPrompt = $this->getSystemPrompt();

                $ch = curl_init('https://api.anthropic.com/v1/messages');
                
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => [
                        'x-api-key: ' . $apiKey,
                        'anthropic-version: 2023-06-01',
                        'content-type: application/json',
                    ],
                    CURLOPT_POSTFIELDS => json_encode([
                        'model' => 'claude-sonnet-4-5-20250929',
                        'max_tokens' => 4096,
                        'stream' => true,
                        'system' => $systemPrompt,
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => $prompt
                            ]
                        ]
                    ]),
                    CURLOPT_WRITEFUNCTION => function($curl, $data) {
                        // Parse and re-stream the data properly
                        echo $data;
                        ob_flush();
                        flush();
                        return strlen($data);
                    }
                ]);

                curl_exec($ch);
                curl_close($ch);

            } catch (\Exception $e) {
                echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    public function saveFromStream(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string',
            'code' => 'required|string',
            'type' => 'required|string',
            'description' => 'nullable|string',
        ]);

        try {
            $history = CodeHistory::create([
                'prompt' => $request->prompt,
                'code' => $request->code,
                'type' => $request->type,
                'description' => $request->description ?? 'Generated code',
                'libraries' => json_encode([]),
            ]);

            return response()->json([
                'success' => true,
                'id' => $history->id
            ]);
        } catch (\Exception $e) {
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
You are a code generation assistant. Respond with ONLY a valid JSON object in this exact format, no markdown, no code blocks, pure JSON:

{
  "type": "react",
  "code": "complete executable code here with proper indentation and formatting",
  "libraries": [],
  "description": "what this code does"
}

Rules:
1. Output ONLY valid JSON, nothing else
2. NO markdown code blocks
3. NO explanations before or after
4. The "type" must be: "react", "html", "javascript", or "vue"
5. Ensure code has proper indentation (2 spaces per level)
6. For React: Include complete component with ReactDOM.render() at the end
7. For HTML: Include full <!DOCTYPE html> structure
8. For JavaScript: Include executable vanilla JS
9. Escape all quotes in the JSON code value as \"
10. Make the code functional and complete

Example output (valid JSON only):
{"type":"react","code":"function App() {\n  const [count, setCount] = React.useState(0);\n  return React.createElement('div', { style: { padding: '20px' } },\n    React.createElement('h1', null, 'Counter: ', count),\n    React.createElement('button', {\n      onClick: () => setCount(count + 1),\n      style: { padding: '10px 20px', background: '#3b82f6', color: 'white', border: 'none', borderRadius: '6px', cursor: 'pointer' }\n    }, 'Increment')\n  );\n}\n\nReactDOM.render(React.createElement(App), document.getElementById('root'));","libraries":[],"description":"A simple counter app"}
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
        // Decode the code string if it's escaped
        $code = $data['code'] ?? '';
        if (is_string($code)) {
            $code = stripslashes($code);
        }

        return [
            'type' => $data['type'] ?? 'javascript',
            'code' => $code,
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