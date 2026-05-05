<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Verdeo') }} — Acceso</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&family=Barlow+Condensed:wght@500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body style="overflow: hidden;">

    <canvas id="verdeo-bg"></canvas>

    <div style="position: relative; z-index: 1; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px;">

        <div style="width: 100%; max-width: 440px;
                    background: rgba(11,24,40,0.72);
                    border: 1px solid rgba(58,125,68,0.35);
                    border-radius: 20px;
                    padding: 44px 40px 40px;
                    backdrop-filter: blur(18px) saturate(1.4);
                    -webkit-backdrop-filter: blur(18px) saturate(1.4);
                    box-shadow: 0 0 0 1px rgba(58,125,68,0.12), 0 24px 60px rgba(0,0,0,0.55), 0 0 80px rgba(58,125,68,0.07) inset;
                    position: relative; overflow: hidden;">

            {{-- Top accent line --}}
            <div style="position: absolute; top: 0; left: 10%; right: 10%; height: 1px;
                        background: linear-gradient(90deg, transparent, rgba(78,158,90,0.7), transparent);"></div>

            {{-- Logo --}}
            <div style="display: flex; justify-content: center; margin-bottom: 28px;">
                <img src="/images/verdeo-logo.png" alt="Verdeo"
                     style="width: 88px; height: 88px; border-radius: 50%; object-fit: cover;
                            filter: drop-shadow(0 4px 18px rgba(58,125,68,0.45));
                            transition: filter 0.4s, transform 0.4s;"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                <div style="display: none; width: 88px; height: 88px; border-radius: 50%;
                            background: linear-gradient(135deg, #3a7d44, #4e9e5a);
                            align-items: center; justify-content: center;
                            font-family: 'Barlow Condensed', sans-serif;
                            font-size: 36px; font-weight: 700; color: white;
                            filter: drop-shadow(0 4px 18px rgba(58,125,68,0.45));">V</div>
            </div>

            {{-- Title --}}
            <div style="text-align: center; margin-bottom: 32px;">
                <h1 style="font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 700;
                           color: #f0f4f0; letter-spacing: 1px; margin-bottom: 4px;">
                    Bienvenido a Verdeo
                </h1>
                <p style="font-size: 13px; color: rgba(240,244,240,0.45);">Panel de gestión · Ingresá con tu cuenta</p>
            </div>

            {{-- Flash errors --}}
            @if($errors->any())
            <div style="margin-bottom: 20px; padding: 12px 16px;
                        background: rgba(220,68,68,0.15);
                        border: 1px solid rgba(220,68,68,0.35);
                        border-radius: 10px; color: #fca5a5; font-size: 13px;">
                {{ $errors->first() }}
            </div>
            @endif

            {{-- Form --}}
            <form method="POST" action="{{ route('login.post') }}" style="display: flex; flex-direction: column; gap: 18px;">
                @csrf

                <div>
                    <label style="display: block; font-family: 'Barlow Condensed', sans-serif;
                                  font-size: 11px; font-weight: 700; letter-spacing: 1.4px;
                                  text-transform: uppercase; color: rgba(240,244,240,0.5);
                                  margin-bottom: 8px; transition: color 0.2s;">
                        Correo electrónico
                    </label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           placeholder="tu@verdeo.com.ar"
                           autocomplete="email" autofocus
                           style="width: 100%; padding: 13px 16px;
                                  background: rgba(255,255,255,0.05);
                                  border: 1px solid rgba(255,255,255,0.10);
                                  border-radius: 10px; color: #f0f4f0;
                                  font-family: 'Barlow', sans-serif; font-size: 14px; outline: none;
                                  transition: border-color 0.25s, background 0.25s, box-shadow 0.25s;">
                </div>

                <div>
                    <label style="display: block; font-family: 'Barlow Condensed', sans-serif;
                                  font-size: 11px; font-weight: 700; letter-spacing: 1.4px;
                                  text-transform: uppercase; color: rgba(240,244,240,0.5);
                                  margin-bottom: 8px;">
                        Contraseña
                    </label>
                    <input type="password" name="password"
                           placeholder="••••••••"
                           autocomplete="current-password"
                           style="width: 100%; padding: 13px 16px;
                                  background: rgba(255,255,255,0.05);
                                  border: 1px solid rgba(255,255,255,0.10);
                                  border-radius: 10px; color: #f0f4f0;
                                  font-family: 'Barlow', sans-serif; font-size: 14px; outline: none;
                                  transition: border-color 0.25s, background 0.25s, box-shadow 0.25s;">
                </div>

                <div style="display: flex; align-items: center; gap: 8px; margin-top: -6px;">
                    <input type="checkbox" name="remember" id="remember"
                           style="width: 15px; height: 15px; border-radius: 4px;
                                  accent-color: #4e9e5a; cursor: pointer;">
                    <label for="remember" style="font-size: 12px; color: rgba(240,244,240,0.45); cursor: pointer;">
                        Mantener sesión iniciada
                    </label>
                </div>

                <button type="submit"
                        style="width: 100%; margin-top: 4px; padding: 14px;
                               border: none; border-radius: 10px; cursor: pointer;
                               background: linear-gradient(135deg, #3a7d44 0%, #4e9e5a 60%, #3a7d44 100%);
                               background-size: 200% 200%; background-position: 100% 100%;
                               color: #fff; font-family: 'Barlow Condensed', sans-serif;
                               font-size: 14px; font-weight: 700; letter-spacing: 2px;
                               text-transform: uppercase;
                               box-shadow: 0 4px 18px rgba(58,125,68,0.35);
                               transition: background-position 0.45s, box-shadow 0.35s, transform 0.2s;"
                        onmouseover="this.style.backgroundPosition='0% 0%'; this.style.boxShadow='0 6px 28px rgba(58,125,68,0.55), 0 0 40px rgba(78,158,90,0.2)'; this.style.transform='translateY(-2px)'"
                        onmouseout="this.style.backgroundPosition='100% 100%'; this.style.boxShadow='0 4px 18px rgba(58,125,68,0.35)'; this.style.transform=''"
                        onmousedown="this.style.transform='translateY(0)'">
                    Ingresar al sistema
                </button>
            </form>

        </div>
    </div>

    {{-- Input focus styles + particle script --}}
    <script>
    document.querySelectorAll('input[type="email"], input[type="password"]').forEach(inp => {
        inp.addEventListener('focus', () => {
            inp.style.borderColor = '#4e9e5a';
            inp.style.background  = 'rgba(58,125,68,0.08)';
            inp.style.boxShadow   = '0 0 0 3px rgba(58,125,68,0.18), 0 0 20px rgba(58,125,68,0.1)';
        });
        inp.addEventListener('blur', () => {
            inp.style.borderColor = 'rgba(255,255,255,0.10)';
            inp.style.background  = 'rgba(255,255,255,0.05)';
            inp.style.boxShadow   = '';
        });
        inp.addEventListener('mouseover', () => {
            if (document.activeElement !== inp) {
                inp.style.borderColor = 'rgba(78,158,90,0.4)';
                inp.style.background  = 'rgba(255,255,255,0.07)';
            }
        });
        inp.addEventListener('mouseout', () => {
            if (document.activeElement !== inp) {
                inp.style.borderColor = 'rgba(255,255,255,0.10)';
                inp.style.background  = 'rgba(255,255,255,0.05)';
            }
        });
    });

    (function () {
        const canvas = document.getElementById('verdeo-bg');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const COLORS = ['#3a7d44', '#4e9e5a', '#c8a030', '#8ab4c8', '#2d6e38'];
        let W, H, particles, mouse = { x: -9999, y: -9999 };
        function resize() { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }
        function Particle() {
            this.x = Math.random() * W; this.y = Math.random() * H;
            this.vx = (Math.random() - 0.5) * 0.4; this.vy = (Math.random() - 0.5) * 0.4;
            this.r = Math.random() * 1.8 + 0.6;
            this.color = COLORS[Math.floor(Math.random() * COLORS.length)];
            this.alpha = Math.random() * 0.5 + 0.2;
        }
        function init() {
            resize();
            particles = Array.from({ length: Math.min(120, Math.floor(W * H / 12000)) }, () => new Particle());
        }
        function draw() {
            ctx.clearRect(0, 0, W, H);
            const maxD = 130;
            for (let i = 0; i < particles.length; i++) {
                const a = particles[i];
                for (let j = i + 1; j < particles.length; j++) {
                    const b = particles[j], dx = a.x - b.x, dy = a.y - b.y, d = Math.sqrt(dx*dx + dy*dy);
                    if (d < maxD) { ctx.beginPath(); ctx.moveTo(a.x,a.y); ctx.lineTo(b.x,b.y); ctx.strokeStyle=`rgba(58,125,68,${0.12*(1-d/maxD)})`; ctx.lineWidth=0.6; ctx.stroke(); }
                }
                const mx = a.x - mouse.x, my = a.y - mouse.y, md = Math.sqrt(mx*mx + my*my);
                if (md < 120) { ctx.beginPath(); ctx.moveTo(a.x,a.y); ctx.lineTo(mouse.x,mouse.y); ctx.strokeStyle=`rgba(78,158,90,${0.18*(1-md/120)})`; ctx.lineWidth=0.8; ctx.stroke(); }
            }
            for (const p of particles) {
                ctx.beginPath(); ctx.arc(p.x,p.y,p.r,0,Math.PI*2);
                ctx.fillStyle=p.color; ctx.globalAlpha=p.alpha; ctx.fill(); ctx.globalAlpha=1;
                p.x+=p.vx; p.y+=p.vy;
                if(p.x<0)p.x=W; if(p.x>W)p.x=0; if(p.y<0)p.y=H; if(p.y>H)p.y=0;
            }
            requestAnimationFrame(draw);
        }
        window.addEventListener('resize', init);
        window.addEventListener('mousemove', e => { mouse.x=e.clientX; mouse.y=e.clientY; });
        window.addEventListener('touchmove', e => { mouse.x=e.touches[0].clientX; mouse.y=e.touches[0].clientY; }, {passive:true});
        init(); draw();
    })();
    </script>
</body>
</html>
