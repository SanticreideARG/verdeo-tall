/* ──────────────────────────────────────────────────────────
   Verdeo Mobile — Colaborador App
   Delivery/driver-facing flow: route, deliveries, status updates
   ────────────────────────────────────────────────────────── */

const ColaboradorApp = () => {
  const [tab, setTab] = React.useState('route');
  const [fabOpen, setFabOpen] = React.useState(false);

  const navItems = [
    { id: 'route', icon: 'map', label: 'Ruta' },
    { id: 'list', icon: 'truck', label: 'Entregas' },
    { id: 'stats', icon: 'star', label: 'Logros' },
    { id: 'profile', icon: 'user', label: 'Perfil' },
  ];

  const fabActions = [
    { id: 'scan', icon: 'qr', label: 'Escanear' },
    { id: 'check', icon: 'check', label: 'Marcar' },
    { id: 'call', icon: 'phone', label: 'Llamar' },
  ];

  return (
    <div style={{
      width: '100%', height: '100%',
      background: VERDEO.bg, color: VERDEO.text,
      fontFamily: 'Barlow, sans-serif',
      position: 'relative', overflow: 'hidden',
      display: 'flex', flexDirection: 'column',
    }}>
      <div style={{
        position: 'absolute', inset: 0, pointerEvents: 'none',
        background: 'radial-gradient(ellipse 70% 50% at 80% 0%, rgba(58,125,68,0.18), transparent 60%), radial-gradient(ellipse 50% 40% at 10% 100%, rgba(200,160,48,0.07), transparent 55%)',
      }} />

      <div className="verdeo-scroll" style={{ flex: 1, overflowY: 'auto', position: 'relative', paddingBottom: 110 }}>

        <TopHeader greeting="Turno activo · 09:42" name="Mateo" avatar="MG" notifCount={1} />

        {/* Today summary card */}
        <div style={{ padding: '0 20px', animation: 'verdeoSlideUp .5s ease' }}>
          <div style={{
            background: 'linear-gradient(135deg, rgba(15,32,53,0.9), rgba(11,24,40,0.9))',
            border: `1px solid ${VERDEO.bdrGreen}`,
            borderRadius: 20, padding: 20, position: 'relative', overflow: 'hidden',
            boxShadow: '0 12px 36px rgba(0,0,0,0.4)',
          }}>
            <div style={{ position: 'absolute', top: 0, left: '15%', right: '15%', height: 1, background: 'linear-gradient(90deg, transparent, rgba(78,158,90,0.7), transparent)' }} />
            <div style={{ fontSize: 10, letterSpacing: 1.4, textTransform: 'uppercase', color: VERDEO.muted, fontWeight: 700, marginBottom: 12 }}>Hoy · 4 May</div>
            <div style={{ display: 'flex', gap: 20, marginBottom: 20 }}>
              <div style={{ flex: 1 }}>
                <div style={{ fontFamily: 'Barlow Condensed, sans-serif', fontSize: 36, fontWeight: 700, lineHeight: 1, color: VERDEO.text }}>12</div>
                <div style={{ fontSize: 11, color: VERDEO.muted, marginTop: 4, letterSpacing: 0.3 }}>Entregas asignadas</div>
              </div>
              <div style={{ width: 1, background: VERDEO.bdr }} />
              <div style={{ flex: 1 }}>
                <div style={{ fontFamily: 'Barlow Condensed, sans-serif', fontSize: 36, fontWeight: 700, lineHeight: 1, color: VERDEO.greenXlt }}>5</div>
                <div style={{ fontSize: 11, color: VERDEO.muted, marginTop: 4, letterSpacing: 0.3 }}>Completadas</div>
              </div>
              <div style={{ width: 1, background: VERDEO.bdr }} />
              <div style={{ flex: 1 }}>
                <div style={{ fontFamily: 'Barlow Condensed, sans-serif', fontSize: 36, fontWeight: 700, lineHeight: 1, color: VERDEO.gold }}>7</div>
                <div style={{ fontSize: 11, color: VERDEO.muted, marginTop: 4, letterSpacing: 0.3 }}>Pendientes</div>
              </div>
            </div>
            {/* Progress */}
            <div style={{ position: 'relative', height: 6, background: 'rgba(255,255,255,0.08)', borderRadius: 999, overflow: 'hidden' }}>
              <div style={{ position: 'absolute', left: 0, top: 0, bottom: 0, width: '42%', background: `linear-gradient(90deg, ${VERDEO.green}, ${VERDEO.greenXlt})`, borderRadius: 999, boxShadow: '0 0 10px rgba(78,158,90,0.5)' }} />
            </div>
            <div style={{ fontSize: 11, color: VERDEO.muted, marginTop: 8, textAlign: 'center' }}>
              42% del turno · estimado fin 14:30
            </div>
          </div>
        </div>

        {/* Next delivery — featured */}
        <div style={{ padding: '24px 20px 0' }}>
          <div style={{ fontFamily: 'Barlow Condensed, sans-serif', fontSize: 13, fontWeight: 700, letterSpacing: 1.4, textTransform: 'uppercase', color: VERDEO.gold, marginBottom: 10, display: 'flex', alignItems: 'center', gap: 8 }}>
            <span style={{ width: 8, height: 8, background: VERDEO.gold, borderRadius: '50%', animation: 'verdeoPulse 1.5s infinite' }} /> Próxima entrega
          </div>
          <div style={{
            background: 'rgba(200,160,48,0.08)',
            border: '1px solid rgba(200,160,48,0.35)',
            borderRadius: 18, padding: 18,
          }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 14 }}>
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: 11, color: VERDEO.muted, letterSpacing: 0.5, marginBottom: 2 }}>#VRD-1284</div>
                <div style={{ fontFamily: 'Barlow Condensed, sans-serif', fontSize: 18, fontWeight: 700, lineHeight: 1.2 }}>Lucía Méndez</div>
                <div style={{ fontSize: 12, color: VERDEO.text, marginTop: 6, display: 'flex', alignItems: 'flex-start', gap: 6 }}>
                  <Icon name="pin" size={14} stroke={VERDEO.gold} />
                  <span>Av. Argentina 1284, 4°B<br/><span style={{ color: VERDEO.muted, fontSize: 11 }}>Neuquén Capital</span></span>
                </div>
              </div>
              <div style={{ textAlign: 'right' }}>
                <div style={{ fontFamily: 'Barlow Condensed, sans-serif', fontSize: 22, fontWeight: 700, color: VERDEO.gold }}>2.4km</div>
                <div style={{ fontSize: 10, color: VERDEO.muted, letterSpacing: 0.5 }}>~ 8 min</div>
              </div>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', padding: '12px 0', borderTop: `1px solid ${VERDEO.bdr}`, borderBottom: `1px solid ${VERDEO.bdr}`, marginBottom: 14 }}>
              <div>
                <div style={{ fontSize: 10, letterSpacing: 0.8, textTransform: 'uppercase', color: VERDEO.muted }}>Pack</div>
                <div style={{ fontSize: 13, fontWeight: 600, marginTop: 2 }}>2× 400g · Keto</div>
              </div>
              <div style={{ textAlign: 'right' }}>
                <div style={{ fontSize: 10, letterSpacing: 0.8, textTransform: 'uppercase', color: VERDEO.muted }}>Cobrar</div>
                <div style={{ fontSize: 13, fontWeight: 600, marginTop: 2, color: VERDEO.greenXlt }}>$ 160.000 · Efectivo</div>
              </div>
            </div>
            <div style={{ display: 'flex', gap: 8 }}>
              <button style={{
                flex: 1, padding: '12px',
                background: `linear-gradient(135deg, ${VERDEO.green}, ${VERDEO.greenLt})`,
                border: 'none', borderRadius: 12, color: '#fff',
                fontFamily: 'Barlow Condensed, sans-serif', fontSize: 12, fontWeight: 700,
                letterSpacing: 1.5, textTransform: 'uppercase', cursor: 'pointer',
                display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6,
                boxShadow: '0 4px 14px rgba(58,125,68,0.4)',
              }}>
                <Icon name="map" size={14} stroke="#fff" /> Iniciar ruta
              </button>
              <button style={{
                width: 48, padding: '12px',
                background: VERDEO.surface, border: `1px solid ${VERDEO.bdr}`,
                borderRadius: 12, color: VERDEO.text, cursor: 'pointer',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
              }}>
                <Icon name="phone" size={16} />
              </button>
            </div>
          </div>
        </div>

        {/* Upcoming deliveries */}
        <div style={{ padding: '24px 20px 0' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: 12 }}>
            <div style={{ fontFamily: 'Barlow Condensed, sans-serif', fontSize: 13, fontWeight: 700, letterSpacing: 1.4, textTransform: 'uppercase', color: VERDEO.text }}>Próximas</div>
            <span style={{ fontSize: 11, color: VERDEO.muted }}>6 restantes</span>
          </div>

          {[
            { id: '1283', name: 'Sofía Bravo', addr: 'Roca 450', city: 'Cipolletti', pack: '1× 250g', dist: '3.1km', stage: 'Preparando', color: VERDEO.greenLt },
            { id: '1282', name: 'Tomás Acosta', addr: 'San Martín 88', city: 'Cipolletti', pack: '3× 400g', dist: '4.6km', stage: 'En depósito', color: VERDEO.muted },
            { id: '1281', name: 'Camila Pérez', addr: 'Mitre 1502', city: 'Neuquén Cap.', pack: '2× 250g', dist: '6.2km', stage: 'Programado', color: VERDEO.muted },
            { id: '1280', name: 'Javier Ríos', addr: 'Alem 320', city: 'Plottier', pack: '1× 400g', dist: '8.0km', stage: 'Programado', color: VERDEO.muted },
          ].map((d) => (
            <div key={d.id} style={{
              display: 'flex', alignItems: 'center', gap: 12,
              padding: 14, background: VERDEO.surface,
              border: `1px solid ${VERDEO.bdr}`, borderRadius: 14,
              marginBottom: 8,
            }}>
              <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 2, minWidth: 38 }}>
                <Icon name="pin" size={16} stroke={d.color} />
                <span style={{ fontSize: 10, fontWeight: 700, color: d.color }}>{d.dist}</span>
              </div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 13, fontWeight: 600 }}>{d.name}</div>
                <div style={{ fontSize: 11, color: VERDEO.muted, marginTop: 2, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{d.addr} · {d.city}</div>
              </div>
              <div style={{ textAlign: 'right' }}>
                <div style={{ fontSize: 11, fontWeight: 600, color: VERDEO.text }}>{d.pack}</div>
                <div style={{ fontSize: 9, letterSpacing: 0.8, textTransform: 'uppercase', color: d.color, fontWeight: 700, marginTop: 2 }}>{d.stage}</div>
              </div>
            </div>
          ))}
        </div>

      </div>

      <LeafDock
        items={navItems}
        active={tab}
        onChange={setTab}
        fabIcon="check"
        onFab={() => setFabOpen(!fabOpen)}
        fabExpanded={fabOpen}
        fabActions={fabActions}
        onFabAction={(id) => { setFabOpen(false); }}
      />
    </div>
  );
};

window.ColaboradorApp = ColaboradorApp;
