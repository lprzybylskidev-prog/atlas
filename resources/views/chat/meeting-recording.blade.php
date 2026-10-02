<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Atlas Meeting recording</title>
        @vite('resources/js/recording-template.ts')
    </head>
    <body>
        <main id="atlas-recording" aria-label="Atlas Meeting recording">
            <section id="screen" aria-label="Shared screen"></section>
            <section id="participants" aria-label="Meeting participants"></section>
            <div id="brand" aria-hidden="true"><span>ATLAS</span><strong>Meeting</strong></div>
        </main>
    </body>
</html>
