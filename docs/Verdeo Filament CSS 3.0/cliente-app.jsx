/* ──────────────────────────────────────────────────────────
   Verdeo Mobile — Cliente App
   Customer-facing flow: home, active order, reorder, history
   ────────────────────────────────────────────────────────── */

const ClienteApp = () => {
  const [tab, setTab] = React.useState('home');
  const [fabOpen, setFabOpen] = React.useState(false);

  const navItems = [
    { id: 'home', icon: 'home', label: 'Inicio' },
    { id: 'orders', icon: 'bag', label: 'Pedidos' },
    { id: 'history', icon: 'history', label: 'Historial' },
    { id: 'profile', icon: 'user', label: 'Perfil' },
  ];

  const fabActions = [
    { id: 'reorder', icon: 'flame', label: 'Repetir' },
    { id: 'new', icon: 'plus', label: 'Nuevo' },
    { id: 'scan', icon: 'qr', label: 'Escanear' },
  ];

  return (
    <div style={{
      width: '100%', height: '100%',
      background: VERDEO.bg,
      color: VERDEO.text,
      fontFamily: 'Barlow, sans-serif',
      position: 'relative',
      overflow: 'hidden',
      display: 'flex', flexDirection: 'column',
    }}>
      {/* Vignettes */}
      <div style={{
        position: 'absolute', inset: 0, pointerEvents: 'none',
        background: 'radial-gradient(ellipse 70% 50% at 20% 0%, rgba(58,125,68,0.18), transparent 60%), radial-gradient(ellipse 50% 40% at 90% 100%, rgba(78,158,90,0.10), transparent 55%)',
      }} />

      {/* Scroll area */}
      <div className="verdeo-scroll" style={{ flex: 1, overflowY: 'auto', position: 'relative', paddingBottom: 110 }}>

        <TopHeader greeting="Buenos días 🌿" name="Lucía" avatar="LM" notifCount={2} />

        {/* Active order card */}
        <div style={{ padding: '0 20px', animation: 'verdeoSlideUp .5s ease' }}>
          <div style={{
            background: 'linear-gradient(135deg, rgba(58,125,68,0.25), rgba(78,158,90,0.12))',
            border: `1px solid ${VERDEO.bdrGreen}`,
            borderRadius: 20, padding: 20,
            position: 'relative', overflow: 'hidden',
            boxShadow: '0 12px 36px rgba(0,0,0,0.35)',
          }}>
            <div style={{
              position: 'absolute', top: 0, left: '15%', right: '15%', height: 1,
              background: 'linear-gradient(90deg, transparent, rgba(78,158,90,0.7), transparent)',
            }} />
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 16 }}>
              <div>
                <div style={{ fontSize: 10, letterSpacing: 1.4, textTransform: 'uppercase', color: VERDEO.greenXlt, fontWeight: 700, marginBottom: 4 }}>● Pedido en camino</div>
                <div style={{ fontFamily: 'Barlow Condensed, sans-serif', fontSize: 24, fontWeight: 700, letterSpacing: 0.5, lineHeight: 1.1 }}>Pack Keto · 400g</div>
                <div style={{ fontSize: 12, color: VERDEO.muted, marginTop: 4 }}>2× unidades · #VRD-1284</div>
              </div>
              <div style={{ textAlign: 'right' }}>
                <div style={{ fontFamily: 'Barlow Condensed, sans-serif', fontSize: 20, fontWeight: 700, color: VERDEO.greenXlt }}>23'</div>
                <div style={{ fontSize: 10, color: VERDEO.muted, letterSpacing: 0.5 }}>llega en</div>
              </div>
            </div>

            {/* Progress */}
            <div style={{ position: 'relative', height: 6, background: 'rgba(255,255,255,0.08)', borderRadius: 999, marginBottom: 14, overflow: 'hidden' }}>
              <div style={{ position: 'absolute', left: 0, top: 0, bottom: 0, width: '65%', background: `linear-gradient(90deg, ${VERDEO.green}, ${VERDEO.greenXlt})`, borderRadius: 999, boxShadow: '0 0 10px rgba(78,158,90,0.5)' }} />
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 10, letterSpacing: 0.8, textTransform: 'uppercase', color: VERDEO.muted, fontWeight: 600 }}>
              <span style={{ color: VERDEO.greenXlt }}>✓ Confirmado</span>
              <span style={{ color: VERDEO.greenXlt }}>✓ Preparando</span>
              <span style={{ color: VERDEO.greenXlt }}>● En camino</span>
              <span>Entregado</span>
            </div>

            <button style={{
              width: '100%', marginTop: 18, padding: '12px',
              background: 'rgba(255,255,255,0.08)',
              border: `1px solid ${VERDEO.bdr}`,
              borderRadius: 12, color: VERDEO.text,
              fontFamily: 'Barlow Condensed, sans-serif', fontSize: 12, fontWeight: 700,
              letterSpacing: 1.5, textTransform: 'uppercase', cursor: 'pointer',
              display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8,
            }}>
              <Icon name="map" size={14} /> Ver en el mapa
            </button>
          </div>
        </div>

        {/* Quick actions */}
        <div style={{ padding: '24px 20px 0', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
          {[
            { icon: 'flame', label: 'Repetir último', sub: 'Pack Keto 400g', color: VERDEO.greenLt },
            { icon: 'leaf', label: 'Explorar packs', sub: '5 opciones', color: VERDEO.gold },
          ].map((a, i) => (
            <button key={i} style={{
              background: VERDEO.surface, border: `1px solid ${VERDEO.bdr}`,
              borderRadius: 16, padding: 14, textAlign: 'left',
              cursor: 'pointer', color: VERDEO.text, transition: 'all .2s',
            }}>
              <div style={{ width: 36, height: 36, borderRadius: 10, background: 'rgba(58,125,68,0.15)', display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: 10, color: a.color }}>
                <Icon name={a.icon} size={18} stroke={a.color} />
              </div>
              <div style={{ fontSize: 13, fontWeight: 600 }}>{a.label}</div>
              <div style={{ fontSize: 11, color: VERDEO.muted, marginTop: 2 }}>{a.sub}</div>
            </button>
          ))}
        </div>

        {/* Recent orders */}
        <div style={{ padding: '24px 20px 0' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: 12 }}>
            <div style={{ fontFamily: 'Barlow Condensed, sans-serif', fontSize: 13, fontWeight: 700, letterSpacing: 1.4, textTransform: 'uppercase', color: VERDEO.text }}>Historial reciente</div>
            <button style={{ background: 'none', border: 'none', color: VERDEO.greenLt, fontSize: 11, fontWeight: 600, letterSpacing: 0.5, cursor: 'pointer' }}>Ver todo →</button>
          </div>

          {[
            { id: '1283', icon: '🥦', name: 'Pack Vegetariano', meta: '1× 250g · 3 May', amount: '$ 65.000', status: 'Entregado', sc: VERDEO.greenXlt },
            { id: '1278', icon: '🥩', name: 'Pack Real', meta: '2× 400g · 28 Abr', amount: '$ 160.000', status: 'Entregado', sc: VERDEO.greenXlt },
            { id: '1265', icon: '✨', name: 'Pack Anti Age', meta: '1× 400g · 22 Abr', amount: '$ 80.000', status: 'Entregado', sc: VERDEO.greenXlt },
          ].map((o, i) => (
            <div key={o.id} style={{
              display: 'flex', alignItems: 'center', gap: 12,
              padding: 14, background: VERDEO.surface,
              border: `1px solid ${VERDEO.bdr}`, borderRadius: 14,
              marginBottom: 8,
            }}>
              <div style={{ width: 40, height: 40, borderRadius: 12, background: 'linear-gradient(135deg, rgba(58,125,68,0.3), rgba(78,158,90,0.15))', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 18 }}>{o.icon}</div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 13, fontWeight: 600 }}>{o.name}</div>
                <div style={{ fontSize: 11, color: VERDEO.muted, marginTop: 2 }}>{o.meta}</div>
              </div>
              <div style={{ textAlign: 'right' }}>
                <div style={{ fontSize: 13, fontWeight: 700, fontFamily: 'Barlow Condensed, sans-serif' }}>{o.amount}</div>
                <div style={{ fontSize: 9, letterSpacing: 0.8, textTransform: 'uppercase', color: o.sc, fontWeight: 700, marginTop: 2 }}>✓ {o.status}</div>
              </div>
            </div>
          ))}
        </div>

      </div>

      {/* Bottom dock */}
      <LeafDock
        items={navItems}
        active={tab}
        onChange={setTab}
        fabIcon="plus"
        onFab={() => setFabOpen(!fabOpen)}
        fabExpanded={fabOpen}
        fabActions={fabActions}
        onFabAction={(id) => { setFabOpen(false); console.log('fab', id); }}
      />
    </div>
  );
};

window.ClienteApp = ClienteApp;
