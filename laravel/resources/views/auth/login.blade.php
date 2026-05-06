<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Verdeo') }} — Acceso</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&family=Barlow+Condensed:wght@500;600;700&display=swap" rel="stylesheet">
    <script>(function(){var t=localStorage.getItem('verdeo-theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        html,body{height:100%;overflow:hidden;}
        [data-theme="light"] body{overflow:auto;}
        .login-stage{
            position:relative;z-index:1;min-height:100vh;
            display:flex;align-items:center;justify-content:center;padding:24px 16px;
        }
        .login-card{
            width:100%;max-width:460px;
            background:var(--vd-surface);
            border:1px solid var(--vd-bdr);
            border-radius:20px;padding:44px 40px 40px;
            backdrop-filter:blur(18px) saturate(1.4);
            -webkit-backdrop-filter:blur(18px) saturate(1.4);
            box-shadow:0 0 0 1px rgba(58,125,68,0.10),var(--vd-shadow),0 0 80px rgba(58,125,68,0.06) inset;
            position:relative;overflow:hidden;
            transition:background 0.3s ease,border-color 0.3s ease;
        }
        .login-card::before{
            content:'';position:absolute;top:0;left:10%;right:10%;height:1px;
            background:linear-gradient(90deg,transparent,rgba(78,158,90,0.7),transparent);
        }
        [data-theme="light"] .login-card::before{
            background:linear-gradient(90deg,transparent,rgba(58,125,68,0.4),transparent);
        }
        .logo-img{
            width:96px;height:96px;border-radius:50%;display:block;
            filter:drop-shadow(0 4px 18px rgba(58,125,68,0.45));
            transition:filter 0.4s ease,transform 0.4s ease;cursor:pointer;
        }
        .logo-img:hover{filter:drop-shadow(0 6px 28px rgba(78,158,90,0.7));transform:scale(1.04) rotate(-1.5deg);}
        .logo-fallback{
            width:96px;height:96px;border-radius:50%;display:none;
            align-items:center;justify-content:center;
            background:linear-gradient(135deg,#3a7d44,#4e9e5a);
            font-family:'Barlow Condensed',sans-serif;font-size:42px;font-weight:700;color:#fff;
            filter:drop-shadow(0 4px 18px rgba(58,125,68,0.45));
            cursor:pointer;transition:filter 0.4s,transform 0.4s;
        }
        .logo-fallback:hover{filter:drop-shadow(0 6px 28px rgba(78,158,90,0.65));transform:scale(1.04) rotate(-1.5deg);}
        .lf{margin-bottom:18px;}
        .lf label{
            display:block;font-family:'Barlow Condensed',sans-serif;
            font-size:11px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;
            color:var(--vd-muted);margin-bottom:8px;transition:color 0.2s;
        }
        .lf:focus-within label{color:var(--vd-green-lt);}
        .lf input{
            width:100%;padding:13px 16px;
            background:var(--vd-input-bg);border:1px solid var(--vd-bdr-soft);
            border-radius:10px;color:var(--vd-text);
            font-family:'Barlow',sans-serif;font-size:15px;outline:none;
            transition:border-color 0.25s,background 0.25s,box-shadow 0.25s;
        }
        .lf input::placeholder{color:var(--vd-muted-2);}
        .lf input:hover{border-color:rgba(78,158,90,0.4);background:rgba(58,125,68,0.05);}
        .lf input:focus{
            border-color:var(--vd-green-lt);background:rgba(58,125,68,0.08);
            box-shadow:0 0 0 3px rgba(58,125,68,0.18),0 0 20px rgba(58,125,68,0.08);
        }
        .login-btn{
            width:100%;margin-top:8px;padding:15px;border:none;border-radius:10px;
            background:linear-gradient(135deg,#3a7d44 0%,#4e9e5a 60%,#3a7d44 100%);
            background-size:200% 200%;background-position:100% 100%;
            color:#fff;font-family:'Barlow Condensed',sans-serif;
            font-size:14px;font-weight:700;letter-spacing:2px;text-transform:uppercase;
            cursor:pointer;transition:background-position 0.45s,box-shadow 0.35s,transform 0.2s;
            box-shadow:0 4px 18px rgba(58,125,68,0.32);
            position:relative;overflow:hidden;
        }
        .login-btn::after{
            content:'';position:absolute;inset:0;
            background:linear-gradient(120deg,transparent 30%,rgba(255,255,255,0.12) 50%,transparent 70%);
            transform:translateX(-100%);transition:transform 0.5s ease;
        }
        .login-btn:hover{background-position:0% 0%;box-shadow:0 6px 28px rgba(58,125,68,0.55),0 0 40px rgba(78,158,90,0.2);transform:translateY(-2px);}
        .login-btn:hover::after{transform:translateX(100%);}
        .login-btn:active{transform:translateY(0);}
        @media(max-width:480px){.login-card{padding:36px 24px 32px;border-radius:16px;}html,body{overflow:auto;}}
    </style>
</head>
<body>

<canvas id="verdeo-bg"></canvas>

<div class="login-stage">
<div class="login-card">

    {{-- Logo --}}
    <div style="display:flex;justify-content:center;margin-bottom:28px;">
        <img src="/images/verdeo-logo.png" alt="Verdeo" class="logo-img"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <div class="logo-fallback">V</div>
    </div>

    {{-- Title --}}
    <div style="text-align:center;margin-bottom:32px;">
        <h1 style="font-family:'Barlow Condensed',sans-serif;font-size:26px;font-weight:700;
                   color:var(--vd-text);letter-spacing:1px;margin-bottom:4px;">
            Bienvenido a Verdeo
        </h1>
        <p style="font-size:13px;color:var(--vd-muted);">Panel de gestión · Ingresá con tu cuenta</p>
    </div>

    {{-- Errors --}}
    @if($errors->any())
    <div style="margin-bottom:20px;padding:12px 16px;
                background:rgba(220,68,68,0.12);border:1px solid rgba(220,68,68,0.32);
                border-radius:10px;color:#fca5a5;font-size:13px;">
        {{ $errors->first() }}
    </div>
    @endif

    {{-- Form --}}
    <form method="POST" action="{{ route('login.post') }}">
        @csrf

        <div class="lf">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   placeholder="tu@verdeo.com.ar" autocomplete="email" autofocus>
        </div>

        <div class="lf">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password"
                   placeholder="••••••••" autocomplete="current-password">
        </div>

        <div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;">
            <input type="checkbox" name="remember" id="remember"
                   style="width:15px;height:15px;accent-color:#4e9e5a;cursor:pointer;">
            <label for="remember" style="font-size:12px;color:var(--vd-muted);cursor:pointer;">
                Mantener sesión iniciada
            </label>
        </div>

        <button type="submit" class="login-btn">Ingresar al sistema</button>
    </form>

    {{-- Theme toggle --}}
    <div style="display:flex;justify-content:center;margin-top:24px;">
        <button onclick="verdeoToggleTheme()" class="theme-toggle" title="Cambiar tema">
            <svg class="ic-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
            </svg>
            <svg class="ic-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
            </svg>
        </button>
    </div>

</div>
</div>

<script>
function verdeoToggleTheme() {
    var cur  = document.documentElement.getAttribute('data-theme') || 'dark';
    var next = cur === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('verdeo-theme', next);
}
(function () {
    const canvas = document.getElementById('verdeo-bg');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const COLORS = ['#3a7d44','#4e9e5a','#c8a030','#8ab4c8','#2d6e38'];
    let W, H, particles, mouse = { x: -9999, y: -9999 };
    function isDark() { return document.documentElement.getAttribute('data-theme') !== 'light'; }
    function resize() { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }
    function Particle() {
        this.x = Math.random()*W; this.y = Math.random()*H;
        this.vx = (Math.random()-.5)*.4; this.vy = (Math.random()-.5)*.4;
        this.r  = Math.random()*1.8+.6;
        this.color = COLORS[Math.floor(Math.random()*COLORS.length)];
        this.alpha = isDark() ? Math.random()*.5+.2 : Math.random()*.22+.07;
    }
    function init() {
        resize();
        particles = Array.from({length: Math.min(120, Math.floor(W*H/12000))}, () => new Particle());
    }
    function draw() {
        ctx.clearRect(0,0,W,H);
        const dark = isDark(), maxD = 130;
        for (let i=0;i<particles.length;i++) {
            const a = particles[i];
            for (let j=i+1;j<particles.length;j++) {
                const b=particles[j],dx=a.x-b.x,dy=a.y-b.y,d=Math.sqrt(dx*dx+dy*dy);
                if(d<maxD){ctx.beginPath();ctx.moveTo(a.x,a.y);ctx.lineTo(b.x,b.y);
                    ctx.strokeStyle=`rgba(58,125,68,${(dark?.12:.05)*(1-d/maxD)})`;ctx.lineWidth=.6;ctx.stroke();}
            }
            const mx=a.x-mouse.x,my=a.y-mouse.y,md=Math.sqrt(mx*mx+my*my);
            if(md<120){ctx.beginPath();ctx.moveTo(a.x,a.y);ctx.lineTo(mouse.x,mouse.y);
                ctx.strokeStyle=`rgba(78,158,90,${(dark?.18:.09)*(1-md/120)})`;ctx.lineWidth=.8;ctx.stroke();}
        }
        for(const p of particles){
            ctx.beginPath();ctx.arc(p.x,p.y,p.r,0,Math.PI*2);
            ctx.fillStyle=p.color;ctx.globalAlpha=p.alpha;ctx.fill();ctx.globalAlpha=1;
            p.x+=p.vx;p.y+=p.vy;
            if(p.x<0)p.x=W;if(p.x>W)p.x=0;if(p.y<0)p.y=H;if(p.y>H)p.y=0;
        }
        requestAnimationFrame(draw);
    }
    window.addEventListener('resize', init);
    window.addEventListener('mousemove', e=>{mouse.x=e.clientX;mouse.y=e.clientY;});
    window.addEventListener('touchmove', e=>{mouse.x=e.touches[0].clientX;mouse.y=e.touches[0].clientY;},{passive:true});
    init(); draw();
})();
</script>
</body>
</html>
