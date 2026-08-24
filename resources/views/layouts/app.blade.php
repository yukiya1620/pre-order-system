<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '予約注文システム')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    {{--
        一般公開デモ専用の操作チュートリアル。DEMO_MODE=true のときだけ読み込む。
        通常運用(DEMO_MODE=false)では、このCSSファイル自体をブラウザが一切
        リクエストしない(HTMLに<link>タグが存在しないため)。
        注文・認証・SMS認証・DB構造・権限制御・在庫・売上・demo:resetの
        いずれにも影響しない、案内専用の見た目レイヤー。
    --}}
    @if (config('demo.enabled'))
        <link rel="stylesheet" href="{{ asset('css/demo-tutorial.css') }}">
    @endif
    @stack('styles')
</head>
<body>
    @yield('content')

    {{--
        チュートリアル用JavaScriptも同様にDEMO_MODE=trueのときだけ読み込む。
        既存の各画面用JS(buyer-home.js等)には一切手を加えない。
        defer付きscriptはHTML中に現れる順序で実行されるため、各画面専用の
        接続コード(buyer-home-tutorial.js等、@stack('scripts')側で読み込む)が
        window.DemoTutorialを参照できるよう、必ずここで先に読み込む。
    --}}
    @if (config('demo.enabled'))
        <script src="{{ asset('js/demo-tutorial.js') }}" defer></script>
    @endif

    @stack('scripts')
</body>
</html>
