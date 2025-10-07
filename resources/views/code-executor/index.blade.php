{{-- resources/views/code-executor/index.blade.php --}}
@extends('code-executor.layouts.app')

@section('title', 'AI Code Executor')

@section('content')
    <div class="flex flex-col h-screen" x-data="codeExecutor()" x-cloak>
        {{-- Header --}}
        <div class="flex items-center justify-between p-4 bg-gray-800 border-b border-gray-700">
            <div class="flex items-center gap-2">
                <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                </svg>
                <h1 class="text-xl font-bold">AI Code Executor</h1>
            </div>

            <div class="flex gap-2 items-center">
                <select x-model="codeType"
                    class="px-3 py-2 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="react">React</option>
                    <option value="html">HTML</option>
                    <option value="javascript">JavaScript</option>
                    <option value="vue">Vue</option>
                </select>

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
        <div class="p-4 bg-gray-800 border-b border-gray-700">
            <div class="flex gap-2">
                <input x-model="aiPrompt" type="text"
                    placeholder="Describe the code you want to generate... (e.g., 'Create a todo list app')"
                    class="flex-1 px-4 py-2 bg-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    @keydown.enter="generateCode" />
                <button @click="generateCode" :disabled="generating"
                    class="flex items-center gap-2 px-6 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    <span x-text="generating ? 'Generating...' : 'Generate with AI'"></span>
                </button>
            </div>
            <div x-show="generationError" class="mt-2 text-red-400 text-sm" x-text="generationError"></div>
        </div>

        {{-- Main Content --}}
        <div class="flex flex-1 overflow-hidden">
            {{-- Code Editor --}}
            <div class="w-1/2 flex flex-col border-r border-gray-700">
                <div
                    class="p-3 bg-gray-800 border-b border-gray-700 text-sm font-semibold flex justify-between items-center">
                    <span>Code Editor</span>
                    <span x-show="codeDescription" class="text-xs text-gray-400" x-text="codeDescription"></span>
                </div>
                <textarea x-model="code" class="flex-1 p-4 bg-gray-900 text-gray-100 font-mono text-sm resize-none focus:outline-none"
                    spellcheck="false" placeholder="Write your code here or use AI to generate..." @keydown.ctrl.enter="executeCode"></textarea>
            </div>

            {{-- Output --}}
            <div class="w-1/2 flex flex-col">
                <div
                    class="p-3 bg-gray-800 border-b border-gray-700 text-sm font-semibold flex items-center justify-between">
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
            <p>💡 Press Ctrl+Enter to run | Describe your app idea and click "Generate with AI" to create code instantly</p>
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

                async generateCode() {
                    if (!this.aiPrompt.trim()) {
                        this.generationError = 'Please enter a description';
                        return;
                    }

                    this.generating = true;
                    this.generationError = '';

                    try {
                        const response = await fetch('{{ route('code-generator.generate') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                prompt: this.aiPrompt
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.code = data.data.code;
                            this.codeType = data.data.type;
                            this.codeDescription = data.data.description;
                            this.generationError = '';

                            setTimeout(() => this.executeCode(), 500);
                        } else {
                            this.generationError = data.message || 'Failed to generate code';
                        }
                    } catch (err) {
                        this.generationError = 'Network error: ' + err.message;
                    } finally {
                        this.generating = false;
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

                clearOutput() {
                    this.$refs.outputFrame.srcdoc = '';
                    this.error = false;
                    this.errorMessage = '';
                }
            }
        }
    </script>
@endpush
