{{-- resources/views/code-executor/index.blade.php --}}
@extends('code-executor.layouts.app')

@section('title', 'AI App Builder')

@section('content')
    <div class="flex h-screen" x-data="codeExecutor()" x-cloak>
        {{-- Sidebar for History --}}
        <div class="w-64 bg-gray-800 border-r border-gray-700 flex flex-col" x-show="showHistory">
            <div class="p-4 border-b border-gray-700">
                <h2 class="text-lg font-bold flex items-center justify-between">
                    <span>History</span>
                    <button @click="clearHistory" class="text-xs text-red-400 hover:text-red-300">Clear All</button>
                </h2>
            </div>
            <div class="flex-1 overflow-y-auto p-2">
                <template x-for="item in history" :key="item.id">
                    <div @click="loadHistory(item.id)"
                        class="p-3 mb-2 bg-gray-700 rounded cursor-pointer hover:bg-gray-600 transition-colors">
                        <p class="text-sm font-semibold truncate" x-text="item.description"></p>
                        <p class="text-xs text-gray-400 truncate mt-1" x-text="item.prompt"></p>
                        <div class="flex items-center justify-between mt-2">
                            <span class="text-xs bg-blue-600 px-2 py-1 rounded" x-text="item.type"></span>
                            <span class="text-xs text-gray-400" x-text="formatDate(item.created_at)"></span>
                        </div>
                    </div>
                </template>
                <template x-if="history.length === 0">
                    <p class="text-center text-gray-500 mt-4">No history yet</p>
                </template>
            </div>
        </div>

        {{-- Main Content --}}
        <div class="flex-1 flex flex-col">
            {{-- Header --}}
            <div class="flex items-center justify-between p-4 bg-[#ffdbb5] border-b border-gray-700">
                <div class="flex items-center gap-2">
                    <button @click="showHistory = !showHistory" class="p-2 hover:bg-gray-700 rounded transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                    </svg>
                    <h1 class="text-xl font-bold">AI App Builder</h1>
                </div>

                <div class="flex gap-2 items-center">
                    <select x-model="codeType"
                        class="px-3 py-2 bg-[#ffeedb] rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="react">React</option>
                        <option value="html">HTML</option>
                        <option value="javascript">JavaScript</option>
                        <option value="vue">Vue</option>
                    </select>

                    <button @click="downloadProject"
                        class="flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        Download
                    </button>

                    <button @click="executeCode" :disabled="loading"
                        class="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg transition-colors disabled:opacity-50">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z">
                            </path>
                        </svg>
                        <span x-text="loading ? 'Running...' : 'Run Code'"></span>
                    </button>

                    <button @click="clearOutput"
                        class="flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                            </path>
                        </svg>
                        Clear
                    </button>
                </div>
            </div>

            {{-- AI Generation Section --}}
            <div class="p-4 bg-[#ffeedb] border-b border-gray-700">
                <div class="flex gap-2 mb-2">
                    <input x-model="aiPrompt" type="text"
                        placeholder="Describe the code you want to generate... (e.g., 'Create a todo list app')"
                        class="flex-1 px-4 py-2 bg-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        @keydown.enter="generateCodeStream" />
                    <button @click="generateCodeStream" :disabled="generating"
                        class="flex items-center gap-2 px-6 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <span x-text="generating ? 'Generating...' : 'Generate with AI'"></span>
                    </button>
                </div>
                <div x-show="generationError" class="text-red-400 text-sm" x-text="generationError"></div>
                <div x-show="streamingProgress" class="text-blue-400 text-sm">
                    <span class="inline-block animate-pulse">●</span> Streaming code...
                </div>
            </div>

            {{-- Main Content --}}
            <div class="flex flex-1 overflow-hidden">
                {{-- Code Editor --}}
                <div class="w-1/2 flex flex-col border-r border-gray-700">
                    <div
                        class="p-3 bg-[#ffeedb] border-b border-gray-700 text-sm font-semibold flex justify-between items-center">
                        <span>Code Editor</span>
                        <span x-show="codeDescription" class="text-xs text-gray-400" x-text="codeDescription"></span>
                    </div>
                    <textarea x-model="code" class="flex-1 p-4 bg-gray-900 text-gray-100 font-mono text-sm resize-none focus:outline-none"
                        spellcheck="false" placeholder="Write your code here or use AI to generate..." @keydown.ctrl.enter="executeCode"></textarea>
                </div>

                {{-- Output --}}
                <div class="w-1/2 flex flex-col">
                    <div
                        class="p-3 bg-[#ffeedb] border-b border-gray-700 text-sm font-semibold flex items-center justify-between">
                        <span>Output</span>
                        <span x-show="loading" class="text-xs text-green-400">Executing...</span>
                        <span x-show="error" class="text-xs text-red-400">Error occurred</span>
                    </div>
                    <div class="flex-1 bg-white overflow-auto">
                        <template x-if="error">
                            <div class="p-4 text-red-600">
                                <h3 class="font-bold mb-2">Error:</h3>
                                <pre class="text-sm bg-red-50 p-3 rounded" x-text="errorMessage"></pre>
                            </div>
                        </template>

                        <iframe x-ref="outputFrame" sandbox="allow-scripts" class="w-full h-full border-0"
                            title="code-output"></iframe>
                    </div>
                </div>
            </div>

            {{-- Info Bar --}}
            <div class="p-3 bg-gray-800 border-t border-gray-700 text-xs text-gray-400">
                <p>💡 Press Ctrl+Enter to run | Describe your app and click "Generate with AI" | Download complete React
                    project with all dependencies</p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function codeExecutor() {
            return {
                code: `// Try React code here or use AI to generate!
function App() {
  const [count, setCount] = React.useState(0);
  
  return (
    React.createElement('div', { style: { padding: '20px', fontFamily: 'system-ui' } },
      React.createElement('h1', null, 'Hello from Laravel!'),
      React.createElement('p', null, 'Count: ', count),
      React.createElement('button', {
        onClick: () => setCount(count + 1),
        style: {
          padding: '10px 20px',
          background: '#3b82f6',
          color: 'white',
          border: 'none',
          borderRadius: '6px',
          cursor: 'pointer'
        }
      }, 'Increment')
    )
  );
}

ReactDOM.render(React.createElement(App), document.getElementById('root'));`,
                codeType: 'react',
                loading: false,
                error: false,
                errorMessage: '',
                aiPrompt: '',
                generating: false,
                generationError: '',
                codeDescription: '',
                showHistory: false,
                history: [],
                streamingProgress: false,
                currentHistoryId: null,

                init() {
                    this.loadHistoryList();
                },

                async loadHistoryList() {
                    try {
                        const response = await fetch('{{ route('code-history.index') }}');
                        const data = await response.json();
                        if (data.success) {
                            this.history = data.data.data; // Pagination wrapper
                        }
                    } catch (err) {
                        console.error('Failed to load history:', err);
                    }
                },

                async loadHistory(id) {
                    try {
                        const response = await fetch(`/code-history/${id}`);
                        const data = await response.json();
                        if (data.success) {
                            this.code = data.data.code;
                            this.codeType = data.data.type;
                            this.codeDescription = data.data.description;
                            this.aiPrompt = data.data.prompt;
                            this.currentHistoryId = id;
                            setTimeout(() => this.executeCode(), 300);
                        }
                    } catch (err) {
                        console.error('Failed to load history item:', err);
                    }
                },

                async clearHistory() {
                    if (!confirm('Are you sure you want to clear all history?')) return;

                    try {
                        const response = await fetch('{{ route('code-history.clear') }}', {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.history = [];
                        }
                    } catch (err) {
                        console.error('Failed to clear history:', err);
                    }
                },

                formatDate(dateStr) {
                    const date = new Date(dateStr);
                    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                },

                async generateCodeStream() {
                    if (!this.aiPrompt.trim()) {
                        this.generationError = 'Please enter a description';
                        return;
                    }

                    this.generating = true;
                    this.streamingProgress = true;
                    this.generationError = '';
                    this.code = ''; // Clear existing code

                    try {
                        const response = await fetch(
                            '{{ route('code-generator.generate-stream') }}?prompt=' + encodeURIComponent(this
                                .aiPrompt), {
                                headers: {
                                    'Accept': 'text/event-stream',
                                }
                            });

                        const reader = response.body.getReader();
                        const decoder = new TextDecoder();
                        let buffer = '';
                        let fullResponse = '';

                        while (true) {
                            const {
                                done,
                                value
                            } = await reader.read();
                            if (done) break;

                            buffer += decoder.decode(value, {
                                stream: true
                            });
                            const lines = buffer.split('\n');
                            buffer = lines.pop();

                            for (const line of lines) {
                                if (line.startsWith('data: ')) {
                                    try {
                                        const data = JSON.parse(line.slice(6));

                                        if (data.type === 'content_block_delta') {
                                            const text = data.delta?.text || '';
                                            fullResponse += text;
                                        }

                                        if (data.type === 'message_stop') {
                                            // Parse the complete response
                                            const codeData = this.parseStreamedCode(fullResponse);
                                            if (codeData) {
                                                this.code = codeData.code;
                                                this.codeType = codeData.type;
                                                this.codeDescription = codeData.description;

                                                // Save to history
                                                await this.saveStreamedCode(codeData);

                                                // Auto-execute
                                                setTimeout(() => this.executeCode(), 500);
                                            }
                                        }
                                    } catch (e) {
                                        // Ignore parse errors for streaming chunks
                                    }
                                }
                            }
                        }
                    } catch (err) {
                        this.generationError = 'Streaming error: ' + err.message;
                    } finally {
                        this.generating = false;
                        this.streamingProgress = false;
                    }
                },

                parseStreamedCode(text) {
                    try {
                        // Try to extract JSON from the response
                        const jsonMatch = text.match(/\{[\s\S]*"code"[\s\S]*\}/);
                        if (jsonMatch) {
                            const parsed = JSON.parse(jsonMatch[0]);
                            return {
                                code: parsed.code || '',
                                type: parsed.type || 'react',
                                description: parsed.description || 'Generated code',
                                libraries: parsed.libraries || []
                            };
                        }
                    } catch (e) {
                        console.error('Parse error:', e);
                    }
                    return null;
                },

                async saveStreamedCode(codeData) {
                    try {
                        const response = await fetch('{{ route('code-generator.save-stream') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                prompt: this.aiPrompt,
                                code: codeData.code,
                                type: codeData.type,
                                description: codeData.description
                            })
                        });

                        const data = await response.json();
                        if (data.success) {
                            this.currentHistoryId = data.id;
                            await this.loadHistoryList();
                        }
                    } catch (err) {
                        console.error('Failed to save history:', err);
                    }
                },

                async executeCode() {
                    this.loading = true;
                    this.error = false;
                    this.errorMessage = '';

                    try {
                        const response = await fetch('{{ route('code-executor.execute') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                code: this.code,
                                type: this.codeType
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.$refs.outputFrame.srcdoc = data.html;
                        } else {
                            this.error = true;
                            this.errorMessage = data.message || 'Unknown error';
                        }
                    } catch (err) {
                        this.error = true;
                        this.errorMessage = err.message;
                    } finally {
                        this.loading = false;
                    }
                },

                async downloadProject() {
                    if (!this.code.trim()) {
                        alert('No code to download!');
                        return;
                    }

                    const projectName = prompt('Enter project name:', 'my-react-app');
                    if (!projectName) return;

                    try {
                        const response = await fetch('{{ route('code-executor.download') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                code: this.code,
                                type: this.codeType,
                                projectName: projectName
                            })
                        });

                        if (response.ok) {
                            const blob = await response.blob();
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = `${projectName}.zip`;
                            document.body.appendChild(a);
                            a.click();
                            window.URL.revokeObjectURL(url);
                            document.body.removeChild(a);
                        } else {
                            const data = await response.json();
                            alert('Download failed: ' + (data.message || 'Unknown error'));
                        }
                    } catch (err) {
                        alert('Download error: ' + err.message);
                    }
                },

                clearOutput() {
                    this.$refs.outputFrame.srcdoc = '';
                    this.error = false;
                    this.errorMessage = '';
                }
            }
        }
    </script>
@endpush
