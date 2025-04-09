
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Custom App</title>
    <script src="{{ asset('js/app.js') }}" defer></script>
    <script src="{{ asset('js/fontawesome.js') }}" defer></script>
    <script src="{{ asset('js/app.js') }}"></script>
    <script src="{{ asset('js/react.js') }}"></script>
    <script src="{{ asset('js/react-dom.js') }}"></script>
    @viteReactRefresh
    @vite(['public/css/app.css','public/css/style.css', 'public/css/all.css', 'public/css/font-awesome.css','resources/js/app.jsx',])
    <style>
       body,
        body *:not(html):not(style):not(br):not(tr):not(code) {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif,
                'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
            box-sizing: border-box;
        }

        body {
            background-color: #f8fafc;
            color: #74787e;
            height: 100%;
            hyphens: auto;
            font-size: 14px;

            line-height: 1.4;
            margin: 0;
            -moz-hyphens: auto;
            -ms-word-break: break-all;
            width: 100% !important;
            -webkit-hyphens: auto;
            -webkit-text-size-adjust: none;
            word-break: break-all;
            word-break: break-word;
        }

        /* .full-height {
            height: 100vh;
        }

        .flex-center {
            align-items: center;
            display: flex;
            justify-content: center;
        }

        .position-ref {
            position: relative;
        }

        .top-right {
            position: absolute;
            right: 10px;
            top: 18px;
        }

        .content {
            text-align: center;
        }

        .title {
            font-size: 84px;
        }

        .links > a {
            color: #636b6f;
            padding: 0 25px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: .1rem;
            text-decoration: none;
            text-transform: uppercase;
        }

        .m-b-md {
            margin-bottom: 30px;
        } */
        a{
            /* color: #1890ff; */
    text-decoration: none;
    background-color: transparent;
    outline: none;
    cursor: pointer;
    /* -webkit-transition: color 0.3s; */
    transition: color 0.3s;
        }
    </style>
</head>
<body>
    <div id="app"></div>
</body>
</html>