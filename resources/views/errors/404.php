<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>404 — Not Found · Sofy</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --bg:#faf6f0; --surf:#ffffff; --border:#efe6da;
    --text:#574d44; --muted:#a89a8b; --bright:#33271d;
    --accent:#d97757; --accent2:#b08fd0;
    --font:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;
}
@media (prefers-color-scheme: dark) {
    :root {
        --bg:#1a1613; --surf:#241e1a; --border:#3a322c;
        --text:#e9ddd0; --muted:#a08f7e; --bright:#fdf6ee;
        --accent:#e8896b; --accent2:#bf9ee0;
    }
}
html, body { min-height: 100%; background: var(--bg); color: var(--text); font-family: var(--font); }

/* soft warm blobs */
body::before {
    content: ''; position: fixed; inset: 0; pointer-events: none; z-index: 0;
    background:
        radial-gradient(60vw 60vw at 6% -8%, rgba(217,119,87,.10) 0%, transparent 60%),
        radial-gradient(55vw 55vw at 99% 6%, rgba(176,143,208,.12) 0%, transparent 60%),
        radial-gradient(55vw 55vw at 50% 112%, rgba(245,201,150,.11) 0%, transparent 62%);
}
/* soft glow orb */
.glow {
    position: fixed; top: -110px; right: -80px;
    width: 320px; height: 320px; border-radius: 50%;
    background: radial-gradient(circle at 38% 36%, rgba(255,226,198,.95) 0%, rgba(233,160,120,.55) 42%, rgba(217,119,87,.22) 68%, transparent 100%);
    filter: blur(6px); opacity: .6; pointer-events: none; z-index: 0;
}

.wrap {
    position: relative; z-index: 1;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    min-height: 100vh; padding: 40px 20px; text-align: center;
}
.code {
    font-size: clamp(96px, 20vw, 184px); font-weight: 800;
    letter-spacing: -.05em; line-height: .9;
    color: transparent;
    background: linear-gradient(135deg, var(--accent) 20%, var(--accent2) 100%);
    -webkit-background-clip: text; background-clip: text;
    margin-bottom: 18px;
    filter: drop-shadow(0 8px 24px rgba(217,119,87,.18));
}
.title { font-size: 20px; color: var(--bright); margin-bottom: 10px; font-weight: 700; letter-spacing: -.01em; }
.msg { font-size: 14px; color: var(--muted); margin-bottom: 36px; max-width: 380px; line-height: 1.8; }
.actions { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; }
.btn {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11px; letter-spacing: .06em; text-transform: uppercase; font-weight: 600;
    padding: 11px 24px; border-radius: 12px; text-decoration: none;
    border: 1px solid transparent; cursor: pointer;
    transition: background .18s ease, border-color .18s ease, transform .18s ease;
}
.btn-primary { color: #fff; background: var(--accent); border-color: var(--accent); box-shadow: 0 3px 12px rgba(217,119,87,.28); }
.btn-primary:hover { transform: translateY(-1px); }
.btn-ghost { color: var(--accent); background: var(--surf); border-color: var(--border); }
.btn-ghost:hover { border-color: var(--accent); }
.brand { position: fixed; bottom: 24px; font-size: 11px; color: var(--muted); letter-spacing: .08em; }
.brand span { color: var(--accent); }
</style>
</head>
<body>
<div class="glow"></div>
<div class="wrap">
    <div class="code">404</div>
    <div class="title">Page not found</div>
    <p class="msg">The page you're looking for doesn't exist, has been moved, or never existed at all.</p>
    <div class="actions">
        <a href="/" class="btn btn-primary">← Home</a>
        <a href="javascript:history.back()" class="btn btn-ghost">Go back</a>
    </div>
</div>
<div class="brand">So<span>fy</span> Framework</div>
</body>
</html>
