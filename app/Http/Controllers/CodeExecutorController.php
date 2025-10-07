<?php
// app/Http/Controllers/CodeExecutorController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
}
