<?php
// app/Http/Controllers/CodeExecutorController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class CodeExecutorController extends Controller
{
    public function index()
    {
        return view('code-executor.index');
    }

    public function execute(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50000',
            'type' => 'required|in:react,html,javascript,vue'
        ]);

        $code = $request->input('code');
        $type = $request->input('type');
        $libraries = $request->input('libraries', []);

        try {
            $htmlContent = $this->generateHtmlContent($code, $type, $libraries);

            return response()->json([
                'success' => true,
                'html' => $htmlContent
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function download(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'type' => 'required|in:react,html,javascript,vue',
            'projectName' => 'nullable|string|max:100'
        ]);

        $code = $request->input('code');
        $type = $request->input('type');
        $projectName = $request->input('projectName', 'my-app');
        
        try {
            if ($type === 'react') {
                return $this->downloadReactProject($code, $projectName);
            } elseif ($type === 'html') {
                return $this->downloadHtmlProject($code, $projectName);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Download not supported for this type yet'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Download failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function uploadGitRepo(Request $request)
    {
        $request->validate([
            'repo_url' => 'required|url'
        ]);

        $repoUrl = $request->input('repo_url');

        try {
            // Sanitize repo URL
            $repoUrl = trim($repoUrl);
            
            // Extract repo name from URL
            preg_match('/\/([^\/]+?)(?:\.git)?$/', $repoUrl, $matches);
            $repoName = $matches[1] ?? 'repo';
            $repoName = preg_replace('/[^a-zA-Z0-9-_]/', '', $repoName);
            
            $tempDir = storage_path('app/temp/git_' . $repoName . '_' . time());
            
            // Clone the repository
            $command = "git clone --depth 1 " . escapeshellarg($repoUrl) . " " . escapeshellarg($tempDir) . " 2>&1";
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new \Exception('Failed to clone repository: ' . implode("\n", $output));
            }

            // Remove .git directory
            $this->deleteDirectory($tempDir . '/.git');

            // Create ZIP file
            $zipPath = storage_path('app/temp/' . $repoName . '.zip');
            $zip = new ZipArchive();
            
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($tempDir),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($tempDir) + 1);
                        $zip->addFile($filePath, $repoName . '/' . $relativePath);
                    }
                }

                $zip->close();
                $this->deleteDirectory($tempDir);

                return response()->download($zipPath)->deleteFileAfterSend(true);
            }

            throw new \Exception('Failed to create ZIP file');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function uploadProject(Request $request)
    {
        $request->validate([
            'project' => 'required|file|mimes:zip|max:51200' // 50MB max
        ]);

        try {
            $file = $request->file('project');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $tempDir = storage_path('app/temp/uploaded_' . time());
            
            mkdir($tempDir, 0755, true);
            
            // Extract ZIP
            $zip = new ZipArchive();
            if ($zip->open($file->getRealPath()) === TRUE) {
                $zip->extractTo($tempDir);
                $zip->close();
            } else {
                throw new \Exception('Failed to extract ZIP file');
            }

            // Find index.html or main entry point
            $htmlFiles = $this->findHtmlFiles($tempDir);
            
            if (empty($htmlFiles)) {
                // Check if it's a React project
                if (file_exists($tempDir . '/package.json')) {
                    $this->deleteDirectory($tempDir);
                    return response()->json([
                        'success' => false,
                        'message' => 'React projects need to be built first. Please run "npm run build" and upload the build folder.'
                    ], 400);
                }
                
                $this->deleteDirectory($tempDir);
                throw new \Exception('No HTML files found in the uploaded project');
            }

            // Use the first HTML file found (prioritize index.html)
            $indexFile = $this->findIndexHtml($htmlFiles) ?? $htmlFiles[0];
            $htmlContent = file_get_contents($indexFile);

            // Fix relative paths in HTML
            $htmlContent = $this->fixRelativePaths($htmlContent, $tempDir, dirname($indexFile));

            // Clean up
            $this->deleteDirectory($tempDir);

            return response()->json([
                'success' => true,
                'html' => $htmlContent
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function findHtmlFiles($dir)
    {
        $htmlFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'html') {
                $htmlFiles[] = $file->getRealPath();
            }
        }

        return $htmlFiles;
    }

    private function findIndexHtml($htmlFiles)
    {
        foreach ($htmlFiles as $file) {
            if (basename($file) === 'index.html') {
                return $file;
            }
        }
        return null;
    }

    private function fixRelativePaths($html, $baseDir, $htmlDir)
    {
        // This is a basic implementation - you might need to enhance it
        // to handle all asset types (CSS, JS, images, etc.)
        return $html;
    }

    private function downloadReactProject($code, $projectName)
    {
        $projectName = preg_replace('/[^a-zA-Z0-9-_]/', '', $projectName);
        $tempDir = storage_path('app/temp/' . $projectName . '_' . time());
        
        // Create project structure
        mkdir($tempDir, 0755, true);
        mkdir($tempDir . '/src', 0755, true);
        mkdir($tempDir . '/public', 0755, true);

        // Create package.json
        $packageJson = [
            'name' => $projectName,
            'version' => '0.1.0',
            'private' => true,
            'dependencies' => [
                'react' => '^18.2.0',
                'react-dom' => '^18.2.0',
                'react-scripts' => '5.0.1'
            ],
            'scripts' => [
                'start' => 'react-scripts start',
                'build' => 'react-scripts build',
                'test' => 'react-scripts test',
                'eject' => 'react-scripts eject'
            ],
            'eslintConfig' => [
                'extends' => ['react-app']
            ],
            'browserslist' => [
                'production' => ['>0.2%', 'not dead', 'not op_mini all'],
                'development' => ['last 1 chrome version', 'last 1 firefox version', 'last 1 safari version']
            ]
        ];
        file_put_contents($tempDir . '/package.json', json_encode($packageJson, JSON_PRETTY_PRINT));

        // Create public/index.html
        $indexHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="theme-color" content="#000000" />
    <meta name="description" content="Generated by AI App Builder" />
    <title>$projectName</title>
</head>
<body>
    <noscript>You need to enable JavaScript to run this app.</noscript>
    <div id="root"></div>
</body>
</html>
HTML;
        file_put_contents($tempDir . '/public/index.html', $indexHtml);

        // Convert inline React code to proper React component with IMPROVED extraction
        $appJs = $this->convertToReactComponent($code);
        file_put_contents($tempDir . '/src/App.js', $appJs);

        // Create index.js
        $indexJs = <<<JS
import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';

const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
);
JS;
        file_put_contents($tempDir . '/src/index.js', $indexJs);

        // Create .gitignore
        $gitignore = <<<TXT
# dependencies
/node_modules
/.pnp
.pnp.js

# testing
/coverage

# production
/build

# misc
.DS_Store
.env.local
.env.development.local
.env.test.local
.env.production.local

npm-debug.log*
yarn-debug.log*
yarn-error.log*
TXT;
        file_put_contents($tempDir . '/.gitignore', $gitignore);

        // Create README.md
        $readme = <<<MD
# $projectName

This project was generated by AI App Builder.

## Available Scripts

In the project directory, you can run:

### \`npm install\`
First, install the dependencies.

### \`npm start\`
Runs the app in development mode.
Open [http://localhost:3000](http://localhost:3000) to view it in your browser.

### \`npm run build\`
Builds the app for production to the \`build\` folder.

## Learn More

You can learn more in the [Create React App documentation](https://facebook.github.io/create-react-app/docs/getting-started).
MD;
        file_put_contents($tempDir . '/README.md', $readme);

        // Create ZIP file
        $zipPath = storage_path('app/temp/' . $projectName . '.zip');
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tempDir),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($tempDir) + 1);
                    $zip->addFile($filePath, $projectName . '/' . $relativePath);
                }
            }

            $zip->close();

            // Clean up temp directory
            $this->deleteDirectory($tempDir);

            return response()->download($zipPath)->deleteFileAfterSend(true);
        }

        throw new \Exception('Failed to create ZIP file');
    }

    private function downloadHtmlProject($code, $projectName)
    {
        $projectName = preg_replace('/[^a-zA-Z0-9-_]/', '', $projectName);
        $tempDir = storage_path('app/temp/' . $projectName . '_' . time());
        
        mkdir($tempDir, 0755, true);

        // Save HTML file
        file_put_contents($tempDir . '/index.html', $code);

        // Create README
        $readme = <<<MD
# $projectName

This HTML project was generated by AI App Builder.

Simply open \`index.html\` in your browser to view the application.
MD;
        file_put_contents($tempDir . '/README.md', $readme);

        // Create ZIP
        $zipPath = storage_path('app/temp/' . $projectName . '.zip');
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tempDir),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($tempDir) + 1);
                    $zip->addFile($filePath, $projectName . '/' . $relativePath);
                }
            }

            $zip->close();
            $this->deleteDirectory($tempDir);

            return response()->download($zipPath)->deleteFileAfterSend(true);
        }

        throw new \Exception('Failed to create ZIP file');
    }

    private function convertToReactComponent($code)
    {
        // Remove any ReactDOM.render calls from the code
        $code = preg_replace('/ReactDOM\.render\([^)]+\)[^;]*;?/s', '', $code);
        $code = preg_replace('/ReactDOM\.createRoot\([^)]+\)\.render\([^)]+\)[^;]*;?/s', '', $code);
        
        // Try to find function App() declaration
        if (preg_match('/function\s+App\s*\([^)]*\)\s*\{([\s\S]*)\}/m', $code, $matches)) {
            $functionBody = $matches[1];
            
            // Check if it uses React.createElement
            if (strpos($functionBody, 'React.createElement') !== false) {
                return "import React from 'react';\n\nfunction App() {\n" . $functionBody . "\n}\n\nexport default App;";
            }
            
            // Check if it uses JSX (has return with tags)
            if (preg_match('/return\s*\(/s', $functionBody)) {
                return "import React from 'react';\n\nfunction App() {\n" . $functionBody . "\n}\n\nexport default App;";
            }
            
            return "import React from 'react';\n\n" . $matches[0] . "\n\nexport default App;";
        }
        
        // Try to find const/let App = () => arrow function
        if (preg_match('/(const|let|var)\s+App\s*=\s*\([^)]*\)\s*=>\s*\{([\s\S]*)\}/m', $code, $matches)) {
            return "import React from 'react';\n\n" . $matches[0] . "\n\nexport default App;";
        }
        
        // Try to find arrow function without braces (implicit return)
        if (preg_match('/(const|let|var)\s+App\s*=\s*\([^)]*\)\s*=>\s*\(/m', $code, $matches)) {
            return "import React from 'react';\n\n" . $code . ";\n\nexport default App;";
        }

        // If code contains JSX-like syntax or React.createElement, wrap it
        if (strpos($code, 'React.createElement') !== false || 
            preg_match('/<[A-Z]/', $code) || 
            strpos($code, 'useState') !== false ||
            strpos($code, 'useEffect') !== false) {
            
            // Extract imports if any
            $imports = '';
            if (preg_match_all('/import\s+.*?;/s', $code, $importMatches)) {
                $imports = implode("\n", $importMatches[0]) . "\n";
                $code = preg_replace('/import\s+.*?;/s', '', $code);
            }
            
            // Clean up the code
            $code = trim($code);
            
            return <<<JS
import React from 'react';
$imports

function App() {
  $code
}

export default App;
JS;
        }

        // Default fallback - create a basic component
        return <<<JS
import React from 'react';

function App() {
  return (
    <div className="App" style={{ padding: '20px', fontFamily: 'system-ui' }}>
      <h1>Generated App</h1>
      <p>The code structure couldn't be automatically converted. Please check the generated App.js file.</p>
      <pre style={{ background: '#f5f5f5', padding: '10px', borderRadius: '5px', overflow: 'auto' }}>
        {`Original code:\n\n$code`}
      </pre>
    </div>
  );
}

export default App;
JS;
    }

    private function deleteDirectory($dir)
    {
        if (!file_exists($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }

    private function generateHtmlContent($code, $type, $libraries = [])
    {
        $libraryScripts = '';
        foreach ($libraries as $lib) {
            $libraryScripts .= "<script src=\"{$lib}\"></script>\n";
        }

        switch ($type) {
            case 'react':
                return $this->generateReactHtml($code, $libraryScripts);
            case 'vue':
                return $this->generateVueHtml($code, $libraryScripts);
            case 'html':
                return $code;
            case 'javascript':
                return $this->generateJavaScriptHtml($code, $libraryScripts);
            default:
                throw new \Exception('Unsupported type');
        }
    }

    private function generateReactHtml($code, $libraryScripts)
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script crossorigin src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    {$libraryScripts}
    <style>
        body { 
            margin: 0; 
            font-family: system-ui, -apple-system, sans-serif;
        }
        #root { min-height: 100vh; }
    </style>
</head>
<body>
    <div id="root"></div>
    <script type="text/babel">
        try {
            {$code}
        } catch (err) {
            document.body.innerHTML = '<div style="color: red; padding: 20px;"><h3>Error:</h3><pre>' + err.message + '</pre></div>';
        }
    </script>
</body>
</html>
HTML;
    }

    private function generateVueHtml($code, $libraryScripts)
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    {$libraryScripts}
    <style>
        body { 
            margin: 0; 
            font-family: system-ui, -apple-system, sans-serif;
        }
    </style>
</head>
<body>
    <div id="app"></div>
    <script>
        try {
            const { createApp } = Vue;
            {$code}
        } catch (err) {
            document.body.innerHTML = '<div style="color: red; padding: 20px;"><h3>Error:</h3><pre>' + err.message + '</pre></div>';
        }
    </script>
</body>
</html>
HTML;
    }

    private function generateJavaScriptHtml($code, $libraryScripts)
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    {$libraryScripts}
    <style>
        body { 
            margin: 0; 
            padding: 20px;
            font-family: system-ui, -apple-system, sans-serif;
        }
    </style>
</head>
<body>
    <div id="output"></div>
    <script>
        try {
            {$code}
        } catch (err) {
            document.body.innerHTML = '<div style="color: red;"><h3>Error:</h3><pre>' + err.message + '</pre></div>';
        }
    </script>
</body>
</html>
HTML;
    }


    public function importRepo(Request $request)
{
    $request->validate([
        'repo_url' => 'required|url'
    ]);

    $repoUrl = $request->input('repo_url');

    try {
        $repoUrl = trim($repoUrl);
        preg_match('/\/([^\/]+?)(?:\.git)?$/', $repoUrl, $matches);
        $repoName = $matches[1] ?? 'repo';
        $repoName = preg_replace('/[^a-zA-Z0-9-_]/', '', $repoName);
        
        $tempDir = storage_path('app/temp/git_' . $repoName . '_' . time());
        
        $command = "git clone --depth 1 " . escapeshellarg($repoUrl) . " " . escapeshellarg($tempDir) . " 2>&1";
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \Exception('Failed to clone repository: ' . implode("\n", $output));
        }

        $this->deleteDirectory($tempDir . '/.git');

        // Find and read main code file
        $htmlFiles = $this->findHtmlFiles($tempDir);
        $codeFile = null;
        $codeType = 'javascript';
        $code = '';

        if (!empty($htmlFiles)) {
            $codeFile = $this->findIndexHtml($htmlFiles) ?? $htmlFiles[0];
            $code = file_get_contents($codeFile);
            $codeType = 'html';
        } else {
            // Look for JS/JSX files
            $jsFiles = glob($tempDir . '/**/*.{js,jsx}', GLOB_BRACE);
            if (!empty($jsFiles)) {
                $codeFile = $jsFiles[0];
                $code = file_get_contents($codeFile);
                $codeType = 'javascript';
            }
        }

        $this->deleteDirectory($tempDir);

        if (empty($code)) {
            throw new \Exception('No readable code files found in repository');
        }

        return response()->json([
            'success' => true,
            'code' => $code,
            'type' => $codeType
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
}
}