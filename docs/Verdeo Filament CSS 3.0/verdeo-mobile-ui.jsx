/* ──────────────────────────────────────────────────────────
   Verdeo Mobile — Cliente & Colaborador apps
   Mobile-first, dark theme, glassmorphic "leaf dock" nav
   ────────────────────────────────────────────────────────── */

const VERDEO = {
  bg: '#0b1828',
  bg2: '#0f2035',
  surface: 'rgba(255,255,255,0.04)',
  surface2: 'rgba(255,255,255,0.07)',
  bdr: 'rgba(255,255,255,0.08)',
  bdrGreen: 'rgba(58,125,68,0.35)',
  green: '#3a7d44',
  greenLt: '#4e9e5a',
  greenXlt: '#6dbf7a',
  gold: '#c8a030',
  text: '#f0f4f0',
  muted: 'rgba(240,244,240,0.55)',
  muted2: 'rgba(240,244,240,0.35)',
};

/* ─────────── Inline icon set ─────────── */
const Icon = ({ name, size = 20, stroke = 'currentColor', fill = 'none', strokeWidth = 1.8 }) => {
  const paths = {
    home: <path d="M3 12l9-9 9 9M5 10v10h14V10"/>,
    bag: <><path d="M6 2l1 4h11l1-4M5 6l2 14h10l2-14"/><circle cx="9" cy="20" r="1.4" fill={stroke}/><circle cx="15" cy="20" r="1.4" fill={stroke}/></>,
    leaf: <><path d="M20 4c-9 0-15 5-15 13 0 1 0 2 .3 3M4 21l16-17M11 12c4 0 7-2 7-5"/></>,
    user: <><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-7 8-7s8 3 8 7"/></>,
    truck: <><rect x="1" y="6" width="14" height="11" rx="1"/><path d="M15 9h4l3 4v4h-7"/><circle cx="6" cy="19" r="2"/><circle cx="18" cy="19" r="2"/></>,
    map: <><path d="M1 5l7-2 8 2 7-2v16l-7 2-8-2-7 2z"/><path d="M8 3v18M16 5v18"/></>,
    qr: <><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM18 14h3M14 18v3M18 18h3v3h-3z"/></>,
    bell: <><path d="M6 8a6 6 0 1112 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10 21a2 2 0 004 0"/></>,
    plus: <><path d="M12 5v14M5 12h14"/></>,
    chevron: <path d="M9 6l6 6-6 6"/>,
    check: <path d="M5 12l5 5L20 7"/>,
    clock: <><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></>,
    pin: <><path d="M12 22s7-7 7-13a7 7 0 10-14 0c0 6 7 13 7 13z"/><circle cx="12" cy="9" r="2.5"/></>,
    flame: <path d="M12 2s4 3 4 8a4 4 0 11-8 0c0-2-2-3-2-3s-2 3-2 7a6 6 0 0012 0c0-7-4-12-4-12z"/>,
    history: <><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 1.5M3 12a9 9 0 011-4"/></>,
    settings: <><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 00-.2-1.7l2-1.5-2-3.4-2.3.9a7 7 0 00-2.9-1.7L13 2h-2l-.6 2.6a7 7 0 00-2.9 1.7l-2.3-.9-2 3.4 2 1.5A7 7 0 005 12c0 .6.1 1.1.2 1.7l-2 1.5 2 3.4 2.3-.9a7 7 0 002.9 1.7L11 22h2l.6-2.6a7 7 0 002.9-1.7l2.3.9 2-3.4-2-1.5c.1-.6.2-1.1.2-1.7z"/></>,
    arrow: <><path d="M5 12h14M13 5l7 7-7 7"/></>,
    phone: <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.79 19.79 0 012.12 4.18 2 2 0 014.11 2h3a2 2 0 012 1.72c.13.96.37 1.9.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.9.33 1.85.57 2.81.7A2 2 0 0122 16.92z"/>,
    star: <path d="M12 2l3 7 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"/>,
  };
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill={fill} stroke={stroke} strokeWidth={strokeWidth} strokeLinecap="round" strokeLinejoin="round" style={{ display: 'block' }}>
      {paths[name]}
    </svg>
  );
};

/* ─────────── Top header (shared) ─────────── */
const TopHeader = ({ greeting, name, avatar, notifCount = 0 }) => (
  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '52px 20px 18px' }}>
    <div>
      <div style={{ fontSize: 13, color: VERDEO.muted, letterSpacing: 0.3 }}>{greeting}</div>
      <div style={{ fontFamily: 'Barlow Condensed, sans-serif', fontSize: 22, fontWeight: 700, color: VERDEO.text, letterSpacing: 0.5, marginTop: 2 }}>{name}</div>
    </div>
    <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
      <button style={{ width: 38, height: 38, borderRadius: 12, background: VERDEO.surface, border: `1px solid ${VERDEO.bdr}`, color: VERDEO.text, display: 'flex', alignItems: 'center', justifyContent: 'center', position: 'relative', cursor: 'pointer' }}>
        <Icon name="bell" size={18} />
        {notifCount > 0 && <span style={{ position: 'absolute', top: 7, right: 8, width: 8, height: 8, background: VERDEO.greenLt, borderRadius: '50%', border: `2px solid ${VERDEO.bg}` }} />}
      </button>
      <div style={{ width: 38, height: 38, borderRadius: '50%', background: `linear-gradient(135deg, ${VERDEO.green}, ${VERDEO.greenLt})`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 700, fontSize: 13, color: '#fff', border: `1px solid ${VERDEO.bdrGreen}` }}>{avatar}</div>
    </div>
  </div>
);

/* ═══════════════════════════════════════════════════════════
   THE LEAF DOCK — creative bottom nav
   - Glassmorphic pill, 4 icons
   - Active item gets a sliding "leaf" pill that morphs position
   - Center FAB (Verdeo leaf) — tap to reveal secondary actions
     unfurling upward in an arc
   ═══════════════════════════════════════════════════════════ */
const LeafDock = ({ items, active, onChange, fabIcon = 'plus', onFab, fabExpanded, fabActions = [], onFabAction }) => {
  // 4 main items, FAB sits in the middle visually (we put it as a 5th button physically)
  // Layout: [item0] [item1] [FAB] [item2] [item3]
  const slots = [items[0], items[1], 'FAB', items[2], items[3]];
  const activeIdx = slots.findIndex((s, i) => s !== 'FAB' && s.id === active);

  return (
    <div style={{ position: 'absolute', left: 0, right: 0, bottom: 24, display: 'flex', justifyContent: 'center', pointerEvents: 'none', zIndex: 20 }}>

      {/* FAB action ring (unfurls upward when expanded) */}
      {fabExpanded && (
        <div style={{ position: 'absolute', bottom: 56, left: '50%', transform: 'translateX(-50%)', display: 'flex', gap: 12, pointerEvents: 'auto', animation: 'verdeoFabRing .35s cubic-bezier(.2,1.4,.4,1)' }}>
          {fabActions.map((a, i) => (
            <button key={a.id} onClick={() => onFabAction(a.id)} style={{
              width: 52, height: 52, borderRadius: 18,
              background: `linear-gradient(135deg, rgba(58,125,68,0.95), rgba(78,158,90,0.95))`,
              border: `1px solid ${VERDEO.bdrGreen}`,
              color: '#fff', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
              gap: 2, cursor: 'pointer', boxShadow: '0 6px 22px rgba(58,125,68,0.5)',
              animation: `verdeoFabItem .4s cubic-bezier(.2,1.4,.4,1) ${i * 60}ms backwards`,
            }}>
              <Icon name={a.icon} size={18} stroke="#fff" />
              <span style={{ fontSize: 8.5, letterSpacing: 0.4, fontWeight: 600, opacity: 0.95 }}>{a.label}</span>
            </button>
          ))}
        </div>
      )}

      {/* Pill */}
      <div style={{
        pointerEvents: 'auto',
        position: 'relative',
        background: 'rgba(11,24,40,0.78)',
        backdropFilter: 'blur(20px) saturate(1.4)',
        WebkitBackdropFilter: 'blur(20px) saturate(1.4)',
        border: `1px solid ${VERDEO.bdrGreen}`,
        borderRadius: 999,
        padding: '8px 10px',
        display: 'flex',
        alignItems: 'center',
        gap: 4,
        boxShadow: '0 12px 40px rgba(0,0,0,0.6), 0 0 30px rgba(58,125,68,0.18)',
      }}>
        {/* Sliding active leaf */}
        {activeIdx >= 0 && (
          <div style={{
            position: 'absolute',
            top: 8, bottom: 8,
            width: 46,
            left: `calc(${activeIdx} * 50px + 10px)`,
            background: `linear-gradient(135deg, ${VERDEO.green} 0%, ${VERDEO.greenLt} 100%)`,
            borderRadius: 999,
            transition: 'left .35s cubic-bezier(.4,1.4,.4,1)',
            boxShadow: '0 4px 14px rgba(58,125,68,0.5), inset 0 1px 0 rgba(255,255,255,0.15)',
            zIndex: 0,
          }} />
        )}
        {slots.map((s, i) => s === 'FAB' ? (
          <button key="fab" onClick={onFab} style={{
            width: 50, height: 50, borderRadius: '50%',
            margin: '-12px 4px',
            background: fabExpanded
              ? `linear-gradient(135deg, ${VERDEO.gold}, #d8b040)`
              : `linear-gradient(135deg, ${VERDEO.green}, ${VERDEO.greenLt})`,
            border: `2px solid ${VERDEO.bg}`,
            color: '#fff',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            cursor: 'pointer',
            boxShadow: '0 6px 20px rgba(58,125,68,0.6), 0 0 0 1px rgba(78,158,90,0.4)',
            transition: 'transform .3s cubic-bezier(.2,1.4,.4,1), background .3s',
            transform: fabExpanded ? 'rotate(45deg) scale(1.05)' : 'rotate(0) scale(1)',
            zIndex: 2, position: 'relative',
          }}>
            <Icon name={fabIcon} size={22} stroke="#fff" strokeWidth={2.2} />
          </button>
        ) : (
          <button key={s.id} onClick={() => onChange(s.id)} style={{
            width: 46, height: 46, borderRadius: 999,
            background: 'transparent', border: 'none',
            color: s.id === active ? '#fff' : VERDEO.muted,
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            cursor: 'pointer',
            position: 'relative', zIndex: 1,
            transition: 'color .25s',
            margin: '0 2px',
          }}>
            <Icon name={s.icon} size={20} stroke={s.id === active ? '#fff' : VERDEO.muted} strokeWidth={s.id === active ? 2 : 1.8} />
          </button>
        ))}
      </div>
    </div>
  );
};

/* Inject keyframes once */
if (typeof document !== 'undefined' && !document.getElementById('verdeo-mobile-anim')) {
  const s = document.createElement('style');
  s.id = 'verdeo-mobile-anim';
  s.textContent = `
    @keyframes verdeoFabRing { from{opacity:0;transform:translateX(-50%) translateY(10px)} to{opacity:1;transform:translateX(-50%) translateY(0)} }
    @keyframes verdeoFabItem { from{opacity:0;transform:translateY(20px) scale(.6)} to{opacity:1;transform:translateY(0) scale(1)} }
    @keyframes verdeoSlideUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
    @keyframes verdeoPulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.6;transform:scale(1.1)} }
    .verdeo-scroll::-webkit-scrollbar{display:none}
    .verdeo-scroll{scrollbar-width:none;-ms-overflow-style:none}
  `;
  document.head.appendChild(s);
}

Object.assign(window, { VERDEO, Icon, TopHeader, LeafDock });
