<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="referrer" content="no-referrer">
    <title>Unsubscribe</title>
</head>
<body>
    <main>
        <h1>Unsubscribe</h1>
        <p>Confirm that you no longer want to receive campaign emails from this sender.</p>
        <form method="POST" action="{{ route('unsubscribe.consume', ['token' => request()->route('token')]) }}">
            @csrf
            <input type="hidden" name="List-Unsubscribe" value="One-Click">
            <button type="submit">Unsubscribe</button>
        </form>
    </main>
</body>
</html>
