import { useState, useEffect, useContext, createContext } from "react";

// ── WASIL BRAND TOKENS ───────────────────────────────────────────
const DARK = {
  bg:"#0A1628",surface:"#111E30",surfaceHi:"#172540",border:"#1E3050",
  primary:"#0D9488",primaryLight:"#14B8A8",primaryDim:"rgba(13,148,136,0.12)",
  orange:"#F97316",orangeLight:"#FB923C",orangeDim:"rgba(249,115,22,0.12)",
  white:"#FFFFFF",textPri:"#F1F5F9",textSec:"#94A3B8",textMuted:"#475569",
  green:"#10B981",greenBg:"rgba(16,185,129,0.12)",red:"#EF4444",redBg:"rgba(239,68,68,0.12)",
  amber:"#F59E0B",amberBg:"rgba(245,158,11,0.12)",blue:"#3B82F6",blueBg:"rgba(59,130,246,0.12)",
  purple:"#8B5CF6",purpleBg:"rgba(139,92,246,0.12)",
};
const LIGHT = {
  bg:"#F0FDFA",surface:"#FFFFFF",surfaceHi:"#F0FDFA",border:"#CCFBF1",
  primary:"#0D9488",primaryLight:"#0F766E",primaryDim:"rgba(13,148,136,0.08)",
  orange:"#F97316",orangeLight:"#EA6C0E",orangeDim:"rgba(249,115,22,0.08)",
  white:"#FFFFFF",textPri:"#1F2937",textSec:"#6B7280",textMuted:"#9CA3AF",
  green:"#16A34A",greenBg:"#DCFCE7",red:"#DC2626",redBg:"#FEE2E2",
  amber:"#D97706",amberBg:"#FEF3C7",blue:"#2563EB",blueBg:"#DBEAFE",
  purple:"#7C3AED",purpleBg:"#EDE9FE",
};

const ThemeContext = createContext(DARK);
const useTheme = () => useContext(ThemeContext);

// ── SHARED UI ────────────────────────────────────────────────────
const Badge = ({ label, type }) => {
  const C = useTheme();
  const map = {
    pending:   { bg: C.amberBg,  color: C.amber  },
    approved:  { bg: C.greenBg,  color: C.green  },
    active:    { bg: C.greenBg,  color: C.green  },
    open:      { bg: C.redBg,    color: C.red    },
    resolved:  { bg: C.blueBg,   color: C.blue   },
    completed: { bg: C.blueBg,   color: C.blue   },
    bidding:   { bg: C.purpleBg, color: C.purple },
    disputed:  { bg: C.redBg,    color: C.red    },
    suspended: { bg: C.redBg,    color: C.red    },
    rejected:  { bg: C.redBg,    color: C.red    },
    new:       { bg: C.greenBg,  color: C.green  },
  };
  const s = map[type] || map.pending;
  return <span style={{ background: s.bg, color: s.color, borderRadius: 20, padding: "3px 10px", fontSize: 11, fontWeight: 700, whiteSpace: "nowrap" }}>{label}</span>;
};

const StatCard = ({ label, value, sub, icon, accent, trend }) => {
  const C = useTheme();
  return (
    <div style={{ background: C.surface, border: `1px solid ${C.border}`, borderRadius: 14, padding: "20px", borderTop: `3px solid ${accent}`, position: "relative", overflow: "hidden", boxShadow: "0 2px 8px rgba(0,0,0,0.06)" }}>
      <div style={{ position: "absolute", top: 16, left: 16, fontSize: 22, opacity: 0.15 }}>{icon}</div>
      <div style={{ fontSize: 11, color: C.textSec, textTransform: "uppercase", letterSpacing: "1px", marginBottom: 8 }}>{label}</div>
      <div style={{ fontSize: 28, fontWeight: 800, color: C.textPri, fontFamily: "Georgia, serif" }}>{value}</div>
      {sub && <div style={{ fontSize: 12, color: trend === "up" ? C.green : trend === "down" ? C.red : C.textSec, marginTop: 4 }}>{sub}</div>}
    </div>
  );
};

const SectionHeader = ({ title, count, action, onAction }) => {
  const C = useTheme();
  return (
    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 16 }}>
      <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
        <div style={{ fontSize: 16, fontWeight: 700, color: C.textPri }}>{title}</div>
        {count !== undefined && <span style={{ background: C.primaryDim, color: C.primary, borderRadius: 20, padding: "2px 10px", fontSize: 12, fontWeight: 700 }}>{count}</span>}
      </div>
      {action && <button onClick={onAction} style={{ background: "none", border: `1px solid ${C.border}`, borderRadius: 8, padding: "6px 14px", color: C.textSec, fontSize: 12, cursor: "pointer" }}>{action}</button>}
    </div>
  );
};

const Th = ({ children }) => {
  const C = useTheme();
  return <th style={{ padding: "10px 14px", textAlign: "right", fontSize: 10, textTransform: "uppercase", letterSpacing: "1px", color: C.textMuted, borderBottom: `1px solid ${C.border}`, whiteSpace: "nowrap" }}>{children}</th>;
};

const Td = ({ children, style: s = {} }) => {
  const C = useTheme();
  return <td style={{ padding: "13px 14px", fontSize: 13, color: C.textPri, borderBottom: `1px solid ${C.border}`, verticalAlign: "middle", textAlign: "right", ...s }}>{children}</td>;
};

const ActionBtn = ({ label, color, bg, onClick }) => (
  <button onClick={onClick} style={{ background: bg, color, border: "none", borderRadius: 8, padding: "5px 12px", fontSize: 12, fontWeight: 700, cursor: "pointer", whiteSpace: "nowrap" }}>{label}</button>
);

// ── HELPERS ──────────────────────────────────────────────────────
const handleTopupApprove = (id, setData) => {
  fetch(`/api/admin/topups/${id}/approve`, {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${localStorage.getItem('admin_token')}`, 'Accept': 'application/json' }
  }).then(() => setData(prev => ({
    ...prev,
    pending_topups: prev.pending_topups.filter(r => r.id !== id),
    stats: { ...prev.stats, pending_topups: prev.stats.pending_topups - 1, pending_actions: prev.stats.pending_actions - 1 }
  })));
};

const handleTopupReject = (id, setData) => {
  const reason = prompt('سبب الرفض:');
  if (!reason) return;
  fetch(`/api/admin/topups/${id}/reject`, {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${localStorage.getItem('admin_token')}`, 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({ reason }),
  }).then(() => setData(prev => ({
    ...prev,
    pending_topups: prev.pending_topups.filter(r => r.id !== id),
    stats: { ...prev.stats, pending_topups: prev.stats.pending_topups - 1, pending_actions: prev.stats.pending_actions - 1 }
  })));
};

// ── PAGES ────────────────────────────────────────────────────────
const DashboardPage = ({ setPage }) => {
  const C = useTheme();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    fetch('/api/admin/dashboard', { headers: { 'Authorization': `Bearer ${localStorage.getItem('admin_token')}`, 'Accept': 'application/json' } })
      .then(r => r.json()).then(d => { setData(d); setLoading(false); }).catch(() => setLoading(false));
  }, []);
  if (loading) return <div style={{ color: C.textSec, padding: 40, textAlign: "center" }}>جاري التحميل...</div>;
  const stats = data?.stats || {};
  return (
    <div>
      <div style={{ marginBottom: 28 }}>
        <div style={{ fontSize: 24, fontWeight: 800, color: C.textPri }}>لوحة التحكم</div>
        <div style={{ fontSize: 14, color: C.textSec, marginTop: 4 }}>{new Date().toLocaleDateString('ar-SD', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</div>
      </div>
      <div style={{ display: "grid", gridTemplateColumns: "repeat(4,1fr)", gap: 16, marginBottom: 28 }}>
        <StatCard label="إجمالي الإيرادات" value={`SDG ${stats.total_revenue ?? 0}`} sub="كل الوقت" icon="💰" accent={C.primary} trend="up" />
        <StatCard label="الطلبات النشطة" value={stats.active_orders ?? 0} sub="قيد التنفيذ" icon="📦" accent={C.orange} trend="up" />
        <StatCard label="الإجراءات المعلقة" value={stats.pending_actions ?? 0} sub={`${stats.pending_topups ?? 0} شحن · ${stats.pending_disputes ?? 0} نزاع`} icon="⚠" accent={C.amber} />
        <StatCard label="المستخدمون" value={stats.total_users ?? 0} sub={`${stats.total_vendors ?? 0} بائع · ${stats.total_drivers ?? 0} سائق`} icon="👥" accent={C.blue} trend="up" />
      </div>
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 20, marginBottom: 20 }}>
        <div style={{ background: C.surface, border: `1px solid ${C.border}`, borderRadius: 14, padding: "20px" }}>
          <SectionHeader title="طلبات الشحن المعلقة" count={stats.pending_topups ?? 0} action="عرض الكل" onAction={() => setPage("topups")} />
          {(data?.pending_topups || []).length === 0 && <div style={{ color: C.textSec, textAlign: "center", padding: 20, fontSize: 13 }}>لا توجد طلبات معلقة</div>}
          {(data?.pending_topups || []).map(r => (
            <div key={r.id} style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "12px 0", borderBottom: `1px solid ${C.border}` }}>
              <div style={{ display: "flex", gap: 6 }}>
                <ActionBtn label="✓ قبول" color={C.green} bg={C.greenBg} onClick={() => handleTopupApprove(r.id, setData)} />
                <ActionBtn label="✗ رفض" color={C.red} bg={C.redBg} onClick={() => handleTopupReject(r.id, setData)} />
              </div>
              <div style={{ textAlign: "right" }}>
                <div style={{ fontSize: 13, fontWeight: 600, color: C.textPri }}>{r.vendor?.name}</div>
                <div style={{ fontSize: 15, fontWeight: 800, color: C.primary }}>SDG {r.amount}</div>
              </div>
            </div>
          ))}
        </div>
        <div style={{ background: C.surface, border: `1px solid ${C.border}`, borderRadius: 14, padding: "20px" }}>
          <SectionHeader title="النزاعات المفتوحة" count={stats.pending_disputes ?? 0} action="عرض الكل" onAction={() => setPage("disputes")} />
          {(data?.open_disputes || []).length === 0 && <div style={{ color: C.textSec, textAlign: "center", padding: 20, fontSize: 13 }}>لا توجد نزاعات</div>}
          {(data?.open_disputes || []).map(d => (
            <div key={d.id} style={{ padding: "12px 0", borderBottom: `1px solid ${C.border}` }}>
              <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 4 }}>
                <span style={{ fontSize: 13, fontWeight: 700, color: C.textPri }}>SDG {d.delivery?.delivery_price}</span>
                <span style={{ fontSize: 12, color: C.red, fontWeight: 700 }}>DS-{d.id} · طلب #{d.delivery?.order?.id}</span>
              </div>
              <div style={{ fontSize: 12, color: C.textSec, marginBottom: 8 }}>{d.reason}</div>
              <button onClick={() => setPage("disputes")} style={{ fontSize: 12, color: C.primary, background: "none", border: "none", cursor: "pointer", padding: 0 }}>عرض وحل ←</button>
            </div>
          ))}
        </div>
      </div>
      <div style={{ background: C.surface, border: `1px solid ${C.border}`, borderRadius: 14, padding: "20px" }}>
        <SectionHeader title="أحدث الطلبات" action="عرض الكل" onAction={() => setPage("orders")} />
        <table style={{ width: "100%", borderCollapse: "collapse" }}>
          <thead><tr>{["رقم الطلب","المنتج","البائع","السائق","المبلغ","الحالة"].map(h => <Th key={h}>{h}</Th>)}</tr></thead>
          <tbody>
            {(data?.recent_orders || []).map(o => (
              <tr key={o.id}>
                <Td><span style={{ color: C.primary, fontFamily: "monospace", fontWeight: 700 }}>WSL-{o.id}</span></Td>
                <Td>{o.product_name}</Td>
                <Td><span style={{ color: C.textSec }}>{o.vendor?.name}</span></Td>
                <Td><span style={{ color: C.textSec }}>{o.delivery?.driver?.name || '—'}</span></Td>
                <Td><span style={{ fontWeight: 700 }}>SDG {o.delivery?.total_charged || '—'}</span></Td>
                <Td><Badge label={o.status.charAt(0).toUpperCase()+o.status.slice(1)} type={o.status} /></Td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
};

const TopupsPage = () => {
  const C = useTheme();
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    fetch('/api/admin/topups', { headers: { 'Authorization': `Bearer ${localStorage.getItem('admin_token')}`, 'Accept': 'application/json' } })
      .then(r => r.json()).then(d => { setItems(d.data || []); setLoading(false); }).catch(() => setLoading(false));
  }, []);
  const approve = (id) => fetch(`/api/admin/topups/${id}/approve`, { method: 'POST', headers: { 'Authorization': `Bearer ${localStorage.getItem('admin_token')}`, 'Accept': 'application/json' } })
    .then(() => setItems(prev => prev.map(r => r.id === id ? { ...r, status: 'approved' } : r)));
  const reject = (id) => {
    const reason = prompt('سبب الرفض:'); if (!reason) return;
    fetch(`/api/admin/topups/${id}/reject`, { method: 'POST', headers: { 'Authorization': `Bearer ${localStorage.getItem('admin_token')}`, 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ reason }) })
      .then(() => setItems(prev => prev.map(r => r.id === id ? { ...r, status: 'rejected' } : r)));
  };
  if (loading) return <div style={{ color: C.textSec, padding: 40, textAlign: "center" }}>جاري التحميل...</div>;
  return (
    <div>
      <div style={{ marginBottom: 28 }}>
        <div style={{ fontSize: 24, fontWeight: 800, color: C.textPri }}>طلبات شحن المحفظة</div>
        <div style={{ fontSize: 14, color: C.textSec, marginTop: 4 }}>التحقق من التحويلات البنكية وإضافة الرصيد</div>
      </div>
      <div style={{ background: C.surface, border: `1px solid ${C.border}`, borderRadius: 14, padding: "20px" }}>
        {items.length === 0 && <div style={{ color: C.textSec, textAlign: "center", padding: 40 }}>لا توجد طلبات</div>}
        <table style={{ width: "100%", borderCollapse: "collapse" }}>
          <thead><tr>{["الرقم","البائع","المبلغ","البنك","رقم الحوالة","التاريخ","الحالة","الإيصال","الإجراءات"].map(h => <Th key={h}>{h}</Th>)}</tr></thead>
          <tbody>
            {items.map(r => (
              <tr key={r.id}>
                <Td><span style={{ color: C.primary, fontFamily: "monospace", fontWeight: 700 }}>TU-{r.id}</span></Td>
                <Td><div style={{ fontWeight: 600 }}>{r.vendor?.name}</div></Td>
                <Td><span style={{ fontSize: 15, fontWeight: 800, color: C.primary }}>SDG {r.amount}</span></Td>
                <Td><span style={{ color: C.textSec }}>{r.bank_name}</span></Td>
                <Td><span style={{ color: C.textSec }}>{r.transfer_reference}</span></Td>
                <Td><span style={{ color: C.textSec, fontSize: 12 }}>{new Date(r.created_at).toLocaleDateString()}</span></Td>
                <Td><Badge label={r.status.charAt(0).toUpperCase()+r.status.slice(1)} type={r.status} /></Td>
                <Td>
                  {r.receipt_url ? (
                    <button onClick={() => window.open(r.receipt_url, '_blank')} style={{ background: C.blueBg, color: C.blue, border: "none", borderRadius: 8, padding: "5px 10px", fontSize: 12, cursor: "pointer" }}>🖼 عرض</button>
                  ) : r.receipt_path ? (
                    <button onClick={() => window.open(`/storage/${r.receipt_path}`, '_blank')} style={{ background: C.blueBg, color: C.blue, border: "none", borderRadius: 8, padding: "5px 10px", fontSize: 12, cursor: "pointer" }}>🖼 عرض</button>
                  ) : (
                    <span style={{ color: C.textMuted, fontSize: 12 }}>لا يوجد</span>
                  )}
                </Td>
                <Td>{r.status === "pending" ? <div style={{ display: "flex", gap: 6 }}><ActionBtn label="✓ قبول" color={C.green} bg={C.greenBg} onClick={() => approve(r.id)} /><ActionBtn label="✗ رفض" color={C.red} bg={C.redBg} onClick={() => reject(r.id)} /></div> : <span style={{ color: C.textMuted, fontSize: 12 }}>—</span>}</Td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
};

const WithdrawalsPage = () => {
  const C = useTheme();
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [approving, setApproving] = useState(null);
  const [txId, setTxId] = useState('');
  const [txFile, setTxFile] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    fetch('/api/admin/withdrawals', {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('admin_token')}`,
        'Accept': 'application/json'
      }
    }).then(r => r.json()).then(d => {
      setItems(d.data || []);
      setLoading(false);
    }).catch(() => setLoading(false));
  }, []);

  const approve = async () => {
    if (!txId) return;
    setSubmitting(true);

    const formData = new FormData();
    formData.append('transaction_id', txId);
    if (txFile) formData.append('transaction_proof', txFile);

    await fetch(`/api/admin/withdrawals/${approving}/approve`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('admin_token')}`,
        'Accept': 'application/json',
      },
      body: formData,
    });

    setItems(prev => prev.map(r =>
      r.id === approving ? { ...r, status: 'approved', transaction_id: txId } : r));
    setApproving(null);
    setTxId('');
    setTxFile(null);
    setSubmitting(false);
  };

  const reject = (id) => {
    const reason = prompt('سبب الرفض:');
    if (!reason) return;
    fetch(`/api/admin/withdrawals/${id}/reject`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('admin_token')}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ reason })
    }).then(() => setItems(prev =>
      prev.map(r => r.id === id ? { ...r, status: 'rejected' } : r)));
  };

  if (loading) return (
    <div style={{ color: C.textSec, padding: 40, textAlign: "center" }}>
      جاري التحميل...
    </div>
  );

  return (
    <>
      {/* ── Approve Modal ── */}
      {approving && (
        <div style={{
          position: 'fixed', inset: 0,
          background: 'rgba(0,0,0,0.5)',
          display: 'flex', alignItems: 'center',
          justifyContent: 'center', zIndex: 1000
        }}>
          <div style={{
            background: C.surface, borderRadius: 16,
            padding: 24, width: 420,
            border: `1px solid ${C.border}`,
            boxShadow: '0 20px 60px rgba(0,0,0,0.3)'
          }}>
            <div style={{
              fontSize: 17, fontWeight: 700,
              color: C.textPri, marginBottom: 20, textAlign: 'right'
            }}>
              ✅ تأكيد تحويل السحب
            </div>

            {/* Transaction ID */}
            <div style={{ marginBottom: 14, textAlign: 'right' }}>
              <div style={{ fontSize: 12, color: C.textSec, marginBottom: 6 }}>
                رقم العملية البنكية *
              </div>
              <input
                value={txId}
                onChange={e => setTxId(e.target.value)}
                placeholder="أدخل رقم العملية"
                style={{
                  width: '100%', padding: '10px 12px',
                  background: C.surfaceHi,
                  border: `1px solid ${txId ? C.green : C.border}`,
                  borderRadius: 8, color: C.textPri,
                  fontSize: 14, textAlign: 'right',
                  boxSizing: 'border-box', outline: 'none',
                }}
              />
            </div>

            {/* File Upload */}
            <div style={{ marginBottom: 20, textAlign: 'right' }}>
              <div style={{ fontSize: 12, color: C.textSec, marginBottom: 6 }}>
                إثبات التحويل — صورة أو PDF (اختياري)
              </div>
              <label style={{
                display: 'flex', alignItems: 'center',
                gap: 10, padding: '10px 12px',
                background: C.surfaceHi,
                border: `1px solid ${txFile ? C.green : C.border}`,
                borderRadius: 8, cursor: 'pointer',
                justifyContent: 'flex-end'
              }}>
                <span style={{ fontSize: 13, color: txFile ? C.green : C.textSec }}>
                  {txFile ? `✅ ${txFile.name}` : 'اختر ملف...'}
                </span>
                <span style={{ fontSize: 18 }}>📎</span>
                <input
                  type="file"
                  accept="image/*,.pdf"
                  style={{ display: 'none' }}
                  onChange={e => setTxFile(e.target.files[0])}
                />
              </label>
            </div>

            {/* Buttons */}
            <div style={{ display: 'flex', gap: 10 }}>
              <button
                onClick={() => { setApproving(null); setTxId(''); setTxFile(null); }}
                style={{
                  flex: 1, padding: '11px',
                  background: C.surfaceHi,
                  border: `1px solid ${C.border}`,
                  borderRadius: 10, color: C.textSec,
                  cursor: 'pointer', fontSize: 14, fontWeight: 600,
                }}
              >
                إلغاء
              </button>
              <button
                onClick={approve}
                disabled={!txId || submitting}
                style={{
                  flex: 2, padding: '11px',
                  background: txId ? C.green : C.border,
                  border: 'none', borderRadius: 10,
                  color: 'white', fontWeight: 700, fontSize: 14,
                  cursor: txId ? 'pointer' : 'default',
                  opacity: submitting ? 0.7 : 1,
                }}
              >
                {submitting ? 'جاري التأكيد...' : '✓ تأكيد التحويل'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── Page ── */}
      <div>
        <div style={{ marginBottom: 28 }}>
          <div style={{ fontSize: 24, fontWeight: 800, color: C.textPri }}>
            طلبات السحب
          </div>
          <div style={{ fontSize: 14, color: C.textSec, marginTop: 4 }}>
            مراجعة ومعالجة طلبات سحب السائقين
          </div>
        </div>

        <div style={{
          background: C.surface, border: `1px solid ${C.border}`,
          borderRadius: 14, padding: "20px"
        }}>
          {items.length === 0 && (
            <div style={{ color: C.textSec, textAlign: "center", padding: 40 }}>
              لا توجد طلبات سحب
            </div>
          )}
          <table style={{ width: "100%", borderCollapse: "collapse" }}>
            <thead>
              <tr>
                {["الرقم","السائق","المبلغ","اسم البنك","اسم الحساب",
                  "رقم الحساب","التاريخ","الحالة","رقم العملية","الإجراءات"]
                  .map(h => <Th key={h}>{h}</Th>)}
              </tr>
            </thead>
            <tbody>
              {items.map(r => (
                <tr key={r.id}>
                  <Td>
                    <span style={{ color: C.orange, fontFamily: "monospace", fontWeight: 700 }}>
                      WD-{r.id}
                    </span>
                  </Td>
                  <Td><div style={{ fontWeight: 600 }}>{r.driver?.name}</div></Td>
                  <Td>
                    <span style={{ fontSize: 15, fontWeight: 800 }}>
                      SDG {r.amount}
                    </span>
                  </Td>
                  <Td>
                    <span style={{ color: C.textSec, fontSize: 12 }}>
                      {r.bank_name}
                    </span>
                  </Td>
                  <Td>
                    <span style={{ color: C.textSec, fontSize: 12 }}>
                      {r.account_name}
                    </span>
                  </Td>
                  <Td>
                    <span style={{ color: C.textSec, fontSize: 12 }}>
                      {r.account_number}
                    </span>
                  </Td>
                  <Td>
                    <span style={{ color: C.textSec, fontSize: 12 }}>
                      {new Date(r.created_at).toLocaleDateString()}
                    </span>
                  </Td>
                  <Td>
                    <Badge
                      label={r.status.charAt(0).toUpperCase() + r.status.slice(1)}
                      type={r.status}
                    />
                  </Td>
                  <Td>
                    {r.transaction_id
                      ? <div>
                          <div style={{ fontSize: 12, fontWeight: 700, color: C.green }}>
                            {r.transaction_id}
                          </div>
                          {r.transaction_proof && (
                            <a
                              href={`/storage/${r.transaction_proof}`}
                              target="_blank"
                              rel="noreferrer"
                              style={{
                                fontSize: 11, color: C.blue,
                                textDecoration: 'underline'
                              }}
                            >
                              📎 عرض الإثبات
                            </a>
                          )}
                        </div>
                      : <span style={{ color: C.textMuted, fontSize: 12 }}>—</span>
                    }
                  </Td>
                  <Td>
                    {r.status === 'pending'
                      ? <div style={{ display: "flex", gap: 6 }}>
                          <ActionBtn
                            label="✓ موافقة"
                            color={C.green}
                            bg={C.greenBg}
                            onClick={() => setApproving(r.id)}
                          />
                          <ActionBtn
                            label="✗ رفض"
                            color={C.red}
                            bg={C.redBg}
                            onClick={() => reject(r.id)}
                          />
                        </div>
                      : <Badge
                          label={r.status === 'approved' ? 'معتمد' : 'مرفوض'}
                          type={r.status === 'approved' ? 'approved' : 'rejected'}
                        />
                    }
                  </Td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </>
  );
};

const DisputesPage = () => {
  const C = useTheme();
  const [items, setItems] = useState([]);
  const [selected, setSelected] = useState(null);
  const [loading, setLoading] = useState(true);
  const [note, setNote] = useState("");
  useEffect(() => {
    fetch('/api/admin/disputes', { headers: { 'Authorization': `Bearer ${localStorage.getItem('admin_token')}`, 'Accept': 'application/json' } })
      .then(r => r.json()).then(d => { setItems(d.data || []); setLoading(false); }).catch(() => setLoading(false));
  }, []);
  const resolve = (disputeId, endpoint, body = {}) => {
    fetch(`/api/admin/disputes/${disputeId}/${endpoint}`, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${localStorage.getItem('admin_token')}`, 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ admin_note: note, ...body }),
    }).then(() => { setItems(prev => prev.map(d => d.id === disputeId ? { ...d, status: 'resolved' } : d)); setSelected(null); setNote(""); });
  };
  if (loading) return <div style={{ color: C.textSec, padding: 40, textAlign: "center" }}>جاري التحميل...</div>;
  return (
    <div>
      <div style={{ marginBottom: 28 }}>
        <div style={{ fontSize: 24, fontWeight: 800, color: C.textPri }}>النزاعات</div>
        <div style={{ fontSize: 14, color: C.textSec, marginTop: 4 }}>مراجعة وحل النزاعات بين البائعين والسائقين</div>
      </div>
      <div style={{ display: "grid", gridTemplateColumns: selected ? "1fr 380px" : "1fr", gap: 20 }}>
        <div style={{ background: C.surface, border: `1px solid ${C.border}`, borderRadius: 14, padding: "20px" }}>
          {items.length === 0 && <div style={{ color: C.textSec, textAlign: "center", padding: 40 }}>لا توجد نزاعات</div>}
          {items.map(d => (
            <div key={d.id} onClick={() => setSelected(d)} style={{ padding: "16px", borderRadius: 10, marginBottom: 10, cursor: "pointer", border: selected?.id === d.id ? `1.5px solid ${C.primary}` : `1px solid ${C.border}`, background: selected?.id === d.id ? C.primaryDim : C.surfaceHi }}>
              <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 6 }}>
                <Badge label={d.status.charAt(0).toUpperCase() + d.status.slice(1)} type={d.status} />
                <span style={{ fontSize: 12, color: C.red, fontWeight: 700 }}>DS-{d.id} · طلب #{d.delivery?.order?.id}</span>
              </div>
              <div style={{ fontSize: 14, color: C.textPri, fontWeight: 600, marginBottom: 4, textAlign: "right" }}>{d.reason}</div>
              <div style={{ display: "flex", gap: 16, fontSize: 12, color: C.textSec, justifyContent: "flex-end" }}>
                <span>SDG {d.delivery?.delivery_price} 💰</span>
                <span>{d.delivery?.driver?.name} 🚗</span>
                <span>{d.delivery?.vendor?.name} 🏪</span>
              </div>
            </div>
          ))}
        </div>
        {selected && (
          <div style={{ background: C.surface, border: `1px solid ${C.border}`, borderRadius: 14, padding: "20px" }}>
            <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 20 }}>
              <button onClick={() => setSelected(null)} style={{ background: "none", border: "none", color: C.textMuted, cursor: "pointer", fontSize: 18 }}>✕</button>
              <div style={{ fontSize: 16, fontWeight: 700, color: C.textPri }}>نزاع DS-{selected.id}</div>
            </div>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10, marginBottom: 16 }}>
              {[{ role: "البائع", name: selected.delivery?.vendor?.name, icon: "🏪", color: C.primary }, { role: "السائق", name: selected.delivery?.driver?.name, icon: "🚗", color: C.orange }].map(p => (
                <div key={p.role} style={{ background: C.surfaceHi, borderRadius: 10, padding: "12px", border: `1px solid ${C.border}`, textAlign: "right" }}>
                  <div style={{ fontSize: 11, color: p.color, fontWeight: 700, marginBottom: 4 }}>{p.role} {p.icon}</div>
                  <div style={{ fontSize: 13, fontWeight: 600, color: C.textPri }}>{p.name}</div>
                </div>
              ))}
            </div>
            <div style={{ background: C.surfaceHi, borderRadius: 10, padding: "14px", marginBottom: 16, border: `1px solid ${C.border}`, textAlign: "right" }}>
              <div style={{ fontSize: 11, color: C.textSec, marginBottom: 6 }}>المشكلة</div>
              <div style={{ fontSize: 13, color: C.textPri, lineHeight: 1.6 }}>{selected.reason}</div>
            </div>
            <div style={{ background: C.surfaceHi, borderRadius: 10, padding: "14px", marginBottom: 16, border: `1px solid ${C.border}`, textAlign: "right" }}>
              <div style={{ fontSize: 11, color: C.textSec, marginBottom: 4 }}>المبلغ المحجوز</div>
              <div style={{ fontSize: 24, fontWeight: 800, color: C.primary }}>SDG {selected.delivery?.total_charged}</div>
            </div>
            {selected.status === 'open' && (
              <>
                <div style={{ marginBottom: 12 }}>
                  <div style={{ fontSize: 11, color: C.textSec, marginBottom: 6, textAlign: "right" }}>ملاحظة المسؤول (اختياري)</div>
                  <textarea value={note} onChange={e => setNote(e.target.value)} placeholder="أضف ملاحظة..." style={{ width: "100%", padding: "10px", background: C.surfaceHi, border: `1px solid ${C.border}`, borderRadius: 8, color: C.textPri, fontSize: 13, resize: "none", minHeight: 70, boxSizing: "border-box", textAlign: "right", direction: "rtl" }} />
                </div>
                <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                  <button onClick={() => resolve(selected.id, 'release-driver')} style={{ padding: "12px", background: C.greenBg, border: `1px solid ${C.green}`, borderRadius: 10, color: C.green, fontWeight: 700, fontSize: 13, cursor: "pointer", textAlign: "right" }}>✓ إرسال المبلغ للسائق — تم التسليم</button>
                  <button onClick={() => resolve(selected.id, 'refund-vendor')} style={{ padding: "12px", background: C.amberBg, border: `1px solid ${C.amber}`, borderRadius: 10, color: C.amber, fontWeight: 700, fontSize: 13, cursor: "pointer", textAlign: "right" }}>↩ استرداد المبلغ للبائع — فشل التسليم</button>
                  <button onClick={() => resolve(selected.id, 'split', { driver_amount: selected.delivery?.delivery_price / 2 })} style={{ padding: "12px", background: C.blueBg, border: `1px solid ${C.blue}`, borderRadius: 10, color: C.blue, fontWeight: 700, fontSize: 13, cursor: "pointer", textAlign: "right" }}>⚖ تقسيم المبلغ — 50/50</button>
                </div>
              </>
            )}
            {selected.status === 'resolved' && <div style={{ textAlign: "center", padding: "16px", color: C.green, fontWeight: 700 }}>✓ تم الحل</div>}
          </div>
        )}
      </div>
    </div>
  );
};

// ── USERS PAGE (Vendors + Drivers) ───────────────────────────────
const UsersPage = ({ type }) => {
  const C = useTheme();
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState(null);

  useEffect(() => {
    fetch(`/api/admin/${type}`, { headers: { 'Authorization': `Bearer ${localStorage.getItem('admin_token')}`, 'Accept': 'application/json' } })
      .then(r => r.json()).then(d => { setData(d.data || []); setLoading(false); }).catch(() => setLoading(false));
  }, [type]);

  const suspend = (id) => fetch(`/api/admin/users/${id}/suspend`, { method: 'POST', headers: { 'Authorization': `Bearer ${localStorage.getItem('admin_token')}`, 'Accept': 'application/json' } })
    .then(() => setData(prev => prev.map(u => u.id === id ? { ...u, is_suspended: true } : u)));

  const restore = (id) => fetch(`/api/admin/users/${id}/restore`, { method: 'POST', headers: { 'Authorization': `Bearer ${localStorage.getItem('admin_token')}`, 'Accept': 'application/json' } })
    .then(() => setData(prev => prev.map(u => u.id === id ? { ...u, is_suspended: false } : u)));

  const approveDriver = (id) => fetch(`/api/admin/drivers/${id}/approve`, { method: 'POST', headers: { 'Authorization': `Bearer ${localStorage.getItem('admin_token')}`, 'Accept': 'application/json' } })
    .then(() => setData(prev => prev.map(u => u.id === id ? { ...u, approval_status: 'approved' } : u)));

  const rejectDriver = (id) => fetch(`/api/admin/drivers/${id}/reject`, { method: 'POST', headers: { 'Authorization': `Bearer ${localStorage.getItem('admin_token')}`, 'Accept': 'application/json' } })
    .then(() => setData(prev => prev.map(u => u.id === id ? { ...u, approval_status: 'rejected' } : u)));

  if (loading) return <div style={{ color: C.textSec, padding: 40, textAlign: "center" }}>جاري التحميل...</div>;
  const title = type === "vendors" ? "البائعون" : "السائقون";

  return (
    <div>
      <div style={{ marginBottom: 28 }}>
        <div style={{ fontSize: 24, fontWeight: 800, color: C.textPri }}>{title}</div>
        <div style={{ fontSize: 14, color: C.textSec, marginTop: 4 }}>إدارة {title} المسجلين في المنصة</div>
      </div>
      <div style={{ display: "grid", gridTemplateColumns: selected ? "1fr 320px" : "1fr", gap: 20 }}>
        <div style={{ background: C.surface, border: `1px solid ${C.border}`, borderRadius: 14, padding: "20px" }}>
          {data.length === 0 && <div style={{ color: C.textSec, textAlign: "center", padding: 40 }}>لا يوجد {title}</div>}
          <table style={{ width: "100%", borderCollapse: "collapse" }}>
            <thead>
              <tr>
                {(type === "vendors"
                  ? ["الرقم","الاسم","الهاتف","الطلبات","المحفظة","تاريخ الانضمام","الحالة","الإجراءات"]
                  : ["الرقم","الاسم","الهاتف","التقييم","المحفظة","تاريخ الانضمام","الحالة","الاعتماد","الإجراءات"]
                ).map(h => <Th key={h}>{h}</Th>)}
              </tr>
            </thead>
            <tbody>
              {data.map(u => (
                <tr key={u.id}>
                  <Td><span style={{ color: C.textMuted, fontFamily: "monospace", fontSize: 12 }}>#{u.id}</span></Td>
                  <Td>
                    <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                      {type === 'drivers' && u.driver_profile?.photo_path ? (
                        <img
                          src={`/storage/${u.driver_profile.photo_path}`}
                          style={{ width: 32, height: 32, borderRadius: "50%", objectFit: "cover" }}
                        />
                      ) : (
                        <div style={{ width: 32, height: 32, borderRadius: "50%", background: `linear-gradient(135deg, ${C.primary}, ${C.primaryLight})`, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 13, fontWeight: 700, color: C.white }}>
                          {u.name?.charAt(0)}
                        </div>
                      )}
                      <div style={{ fontWeight: 600, cursor: "pointer", color: C.primary }} onClick={() => setSelected(u)}>{u.name}</div>
                    </div>
                  </Td>
                  <Td><span style={{ color: C.textSec, fontSize: 12 }}>{u.phone}</span></Td>
                  {type === "vendors"
                    ? <Td>{u.delivery_orders_count ?? 0} طلب</Td>
                    : <Td><span style={{ color: C.orange }}>★ {u.driver_profile?.rating ?? '—'}</span></Td>
                  }
                  <Td><span style={{ fontWeight: 700, color: C.primary }}>SDG {u.wallet?.balance ?? 0}</span></Td>
                  <Td><span style={{ color: C.textSec, fontSize: 12 }}>{new Date(u.created_at).toLocaleDateString()}</span></Td>
                  <Td><Badge label={u.is_suspended ? "موقوف" : "نشط"} type={u.is_suspended ? "suspended" : "active"} /></Td>

                  {/* Approval column for drivers only */}
                  {type === "drivers" && (
                    <Td>
                      {u.approval_status === 'pending' ? (
                        <div style={{ display: "flex", gap: 4 }}>
                          <ActionBtn label="✓ قبول" color={C.green} bg={C.greenBg} onClick={() => approveDriver(u.id)} />
                          <ActionBtn label="✗ رفض" color={C.red} bg={C.redBg} onClick={() => rejectDriver(u.id)} />
                        </div>
                      ) : (
                        <Badge
                          label={u.approval_status === 'approved' ? 'معتمد' : 'مرفوض'}
                          type={u.approval_status === 'approved' ? 'approved' : 'rejected'}
                        />
                      )}
                    </Td>
                  )}

                  <Td>
                    <div style={{ display: "flex", gap: 6 }}>
                      <ActionBtn label="عرض" color={C.blue} bg={C.blueBg} onClick={() => setSelected(u)} />
                      {u.is_suspended
                        ? <ActionBtn label="استعادة" color={C.green} bg={C.greenBg} onClick={() => restore(u.id)} />
                        : <ActionBtn label="إيقاف" color={C.red} bg={C.redBg} onClick={() => suspend(u.id)} />
                      }
                    </div>
                  </Td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* User Detail Panel */}
        {selected && (
          <div style={{ background: C.surface, border: `1px solid ${C.border}`, borderRadius: 14, padding: "20px" }}>
            <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 20 }}>
              <button onClick={() => setSelected(null)} style={{ background: "none", border: "none", color: C.textMuted, cursor: "pointer", fontSize: 18 }}>✕</button>
              <div style={{ fontSize: 16, fontWeight: 700, color: C.textPri }}>تفاصيل المستخدم</div>
            </div>
            <div style={{ textAlign: "center", marginBottom: 20 }}>
              {type === 'drivers' && selected.driver_profile?.photo_path ? (
                <img
                  src={`/storage/${selected.driver_profile.photo_path}`}
                  alt={selected.name}
                  style={{ width: 80, height: 80, borderRadius: "50%", objectFit: "cover", margin: "0 auto 10px", display: "block", border: `3px solid ${C.primary}` }}
                />
              ) : (
                <div style={{ width: 80, height: 80, borderRadius: "50%", background: `linear-gradient(135deg, ${C.primary}, ${C.primaryLight})`, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 28, fontWeight: 700, color: C.white, margin: "0 auto 10px" }}>{selected.name?.charAt(0)}</div>
              )}
              <div style={{ fontSize: 16, fontWeight: 700, color: C.textPri }}>{selected.name}</div>
              <div style={{ fontSize: 13, color: C.textSec }}>{selected.phone}</div>
              {selected.national_id && <div style={{ fontSize: 12, color: C.textSec, marginTop: 4 }}>الرقم الوطني: {selected.national_id}</div>}
              <div style={{ marginTop: 8 }}><Badge label={selected.is_suspended ? "موقوف" : "نشط"} type={selected.is_suspended ? "suspended" : "active"} /></div>
            </div>

            {/* Driver approval in detail panel */}
            {type === 'drivers' && (
              <div style={{ marginBottom: 12, padding: "12px", background: C.surfaceHi, borderRadius: 10, border: `1px solid ${C.border}`, textAlign: "right" }}>
                <div style={{ fontSize: 11, color: C.textSec, marginBottom: 8 }}>حالة الاعتماد</div>
                {selected.approval_status === 'pending' ? (
                  <div style={{ display: "flex", gap: 8 }}>
                    <button onClick={() => { approveDriver(selected.id); setSelected({ ...selected, approval_status: 'approved' }); }}
                      style={{ flex: 1, padding: "8px", background: C.greenBg, border: `1px solid ${C.green}`, borderRadius: 8, color: C.green, fontWeight: 700, cursor: "pointer" }}>✓ قبول السائق</button>
                    <button onClick={() => { rejectDriver(selected.id); setSelected({ ...selected, approval_status: 'rejected' }); }}
                      style={{ flex: 1, padding: "8px", background: C.redBg, border: `1px solid ${C.red}`, borderRadius: 8, color: C.red, fontWeight: 700, cursor: "pointer" }}>✗ رفض السائق</button>
                  </div>
                ) : (
                  <Badge label={selected.approval_status === 'approved' ? 'معتمد ✅' : 'مرفوض ❌'} type={selected.approval_status === 'approved' ? 'approved' : 'rejected'} />
                )}
              </div>
            )}

            <div style={{ background: C.surfaceHi, borderRadius: 10, padding: "14px", marginBottom: 12, border: `1px solid ${C.border}`, textAlign: "right" }}>
              <div style={{ fontSize: 11, color: C.textSec, marginBottom: 4 }}>رصيد المحفظة</div>
              <div style={{ fontSize: 24, fontWeight: 800, color: C.primary }}>SDG {selected.wallet?.balance ?? 0}</div>
            </div>

            {type === 'drivers' && selected.driver_profile && (
              <div style={{ background: C.surfaceHi, borderRadius: 10, padding: "14px", marginBottom: 12, border: `1px solid ${C.border}`, textAlign: "right" }}>
                <div style={{ fontSize: 11, color: C.textSec, marginBottom: 8 }}>معلومات المركبة</div>
                <div style={{ fontSize: 13, color: C.textPri }}>🚗 {selected.driver_profile.vehicle_type ?? '—'}</div>
                <div style={{ fontSize: 12, color: C.textSec }}>{selected.driver_profile.vehicle_model ?? ''} {selected.driver_profile.vehicle_plate ?? ''}</div>
              </div>
            )}

            <div style={{ display: "flex", gap: 8 }}>
              {selected.is_suspended
                ? <button onClick={() => { restore(selected.id); setSelected({ ...selected, is_suspended: false }); }} style={{ flex: 1, padding: "10px", background: C.greenBg, border: `1px solid ${C.green}`, borderRadius: 10, color: C.green, fontWeight: 700, cursor: "pointer" }}>✓ استعادة الحساب</button>
                : <button onClick={() => { suspend(selected.id); setSelected({ ...selected, is_suspended: true }); }} style={{ flex: 1, padding: "10px", background: C.redBg, border: `1px solid ${C.red}`, borderRadius: 10, color: C.red, fontWeight: 700, cursor: "pointer" }}>✕ إيقاف الحساب</button>
              }
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

const SettingsPage = () => {
  const C = useTheme();
  const [minVersion, setMinVersion] = useState('1.0.0');
  const [latestVersion, setLatestVersion] = useState('1.0.0');
  const [forceUpdate, setForceUpdate] = useState(false);
  const [message, setMessage] = useState('يوجد تحديث جديد للتطبيق. يرجى التحديث للاستمرار.');
  const [whatsapp, setWhatsapp] = useState('249900000000');
  const [bankName, setBankName] = useState('بنك الخرطوم');
  const [accountName, setAccountName] = useState('وصل للتوصيل');
  const [accountNumber, setAccountNumber] = useState('1234567890');
  const [saved, setSaved] = useState(false);
  const [loading, setLoading] = useState(true);

  // ← All hooks first, then fetch in useEffect
  useEffect(() => {
    // Load version
    fetch('/api/admin/app-version', {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('admin_token')}`,
        'Accept': 'application/json'
      }
    }).then(r => r.json()).then(d => {
      if (d) {
        setMinVersion(d.minimum_version ?? '1.0.0');
        setLatestVersion(d.latest_version ?? '1.0.0');
        setForceUpdate(d.force_update ?? false);
        setMessage(d.update_message ?? 'يوجد تحديث جديد للتطبيق. يرجى التحديث للاستمرار.');
      }
    }).catch(() => {});

    // Load settings
    fetch('/api/settings', {
      headers: { 'Accept': 'application/json' }
    }).then(r => r.json()).then(d => {
      if (d) {
        setWhatsapp(d.support_whatsapp ?? '249900000000');
        setBankName(d.bank_name ?? 'بنك الخرطوم');
        setAccountName(d.account_name ?? 'وصل للتوصيل');
        setAccountNumber(d.account_number ?? '1234567890');
      }
      setLoading(false);
    }).catch(() => setLoading(false));
  }, []);

  if (loading) return <div style={{ color: C.textSec, padding: 40, textAlign: "center" }}>جاري التحميل...</div>;

  const save = () => {
    fetch('/api/admin/app-version', {
      method: 'PUT',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('admin_token')}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        platform:        'android',
        minimum_version: minVersion,
        latest_version:  latestVersion,
        force_update:    forceUpdate,
        update_message:  message,
        update_url:      'https://play.google.com/store/apps/details?id=com.example.wasil_app',
      }),
    });

    fetch('/api/admin/settings', {
      method: 'PUT',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('admin_token')}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        support_whatsapp: whatsapp,
        bank_name:        bankName,
        account_name:     accountName,
        account_number:   accountNumber,
      }),
    }).then(() => { setSaved(true); setTimeout(() => setSaved(false), 3000); });
  };

  return (
    <div>
      <div style={{ marginBottom: 28 }}>
        <div style={{ fontSize: 24, fontWeight: 800, color: C.textPri }}>إعدادات التطبيق</div>
        <div style={{ fontSize: 14, color: C.textSec, marginTop: 4 }}>إدارة إصدارات التطبيق والتحديثات الإجبارية</div>
      </div>
      <div style={{ background: C.surface, border: `1px solid ${C.border}`, borderRadius: 14, padding: "24px", maxWidth: 500 }}>

        <div style={{ fontSize: 16, fontWeight: 700, color: C.textPri, marginBottom: 20, textAlign: "right" }}>🔄 إعدادات الإصدار — Android</div>

        {[
          { label: 'الإصدار الأدنى المطلوب', value: minVersion, onChange: setMinVersion, hint: 'مثال: 1.0.5' },
          { label: 'أحدث إصدار', value: latestVersion, onChange: setLatestVersion, hint: 'مثال: 1.2.0' },
        ].map(f => (
          <div key={f.label} style={{ marginBottom: 16, textAlign: "right" }}>
            <div style={{ fontSize: 13, color: C.textSec, marginBottom: 6 }}>{f.label}</div>
            <input value={f.value} onChange={e => f.onChange(e.target.value)} placeholder={f.hint}
              style={{ width: "100%", padding: "10px 12px", background: C.surfaceHi, border: `1px solid ${C.border}`, borderRadius: 8, color: C.textPri, fontSize: 14, textAlign: "right", boxSizing: "border-box" }} />
          </div>
        ))}

        <div style={{ fontSize: 16, fontWeight: 700, color: C.textPri, margin: "20px 0 16px", textAlign: "right" }}>📞 بيانات الدعم والبنك</div>

        {[
          { label: 'رقم واتساب الدعم', value: whatsapp, onChange: setWhatsapp, hint: '249900000000' },
          { label: 'اسم البنك', value: bankName, onChange: setBankName, hint: 'بنك الخرطوم' },
          { label: 'اسم الحساب', value: accountName, onChange: setAccountName, hint: 'وصل للتوصيل' },
          { label: 'رقم الحساب', value: accountNumber, onChange: setAccountNumber, hint: '1234567890' },
        ].map(f => (
          <div key={f.label} style={{ marginBottom: 16, textAlign: "right" }}>
            <div style={{ fontSize: 13, color: C.textSec, marginBottom: 6 }}>{f.label}</div>
            <input value={f.value} onChange={e => f.onChange(e.target.value)} placeholder={f.hint}
              style={{ width: "100%", padding: "10px 12px", background: C.surfaceHi, border: `1px solid ${C.border}`, borderRadius: 8, color: C.textPri, fontSize: 14, textAlign: "right", boxSizing: "border-box" }} />
          </div>
        ))}

        <div style={{ marginBottom: 16, textAlign: "right" }}>
          <div style={{ fontSize: 13, color: C.textSec, marginBottom: 6 }}>رسالة التحديث</div>
          <textarea value={message} onChange={e => setMessage(e.target.value)}
            style={{ width: "100%", padding: "10px 12px", background: C.surfaceHi, border: `1px solid ${C.border}`, borderRadius: 8, color: C.textPri, fontSize: 14, textAlign: "right", direction: "rtl", resize: "none", minHeight: 80, boxSizing: "border-box" }} />
        </div>

        <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 20, justifyContent: "flex-end" }}>
          <div style={{ fontSize: 13, color: C.textSec }}>تحديث إجباري</div>
          <div onClick={() => setForceUpdate(!forceUpdate)}
            style={{ width: 44, height: 24, borderRadius: 12, background: forceUpdate ? C.primary : C.border, cursor: "pointer", position: "relative", transition: "background 0.2s" }}>
            <div style={{ width: 20, height: 20, borderRadius: "50%", background: "white", position: "absolute", top: 2, left: forceUpdate ? 22 : 2, transition: "left 0.2s" }} />
          </div>
        </div>

        <button onClick={save} style={{ width: "100%", padding: "12px", background: C.primary, border: "none", borderRadius: 10, color: "white", fontWeight: 700, fontSize: 15, cursor: "pointer" }}>
          {saved ? '✅ تم الحفظ!' : 'حفظ الإعدادات'}
        </button>
      </div>
    </div>
  );
};

const TermsPage = () => {
  const C = useTheme();
  const [vendorTerms, setVendorTerms] = useState('');
  const [driverTerms, setDriverTerms] = useState('');
  const [saved, setSaved] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch('/api/admin/terms', {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('admin_token')}`,
        'Accept': 'application/json',
      }
    }).then(r => r.json()).then(d => {
      setVendorTerms(d.vendor_terms ?? '');
      setDriverTerms(d.driver_terms ?? '');
      setLoading(false);
    }).catch(() => setLoading(false));
  }, []);

  const save = () => {
    fetch('/api/admin/terms', {
      method: 'PUT',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('admin_token')}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        vendor_terms: vendorTerms,
        driver_terms: driverTerms,
      }),
    }).then(() => {
      setSaved(true);
      setTimeout(() => setSaved(false), 3000);
    });
  };

  if (loading) return (
    <div style={{ color: C.textSec, padding: 40, textAlign: "center" }}>
      جاري التحميل...
    </div>
  );

  return (
    <div>
      <div style={{ marginBottom: 28 }}>
        <div style={{ fontSize: 24, fontWeight: 800, color: C.textPri }}>
          الشروط والسياسات
        </div>
        <div style={{ fontSize: 14, color: C.textSec, marginTop: 4 }}>
          إدارة شروط الاستخدام لكل من البائعين والسائقين
        </div>
      </div>

      {/* Warning */}
      <div style={{
        background: C.amberBg, border: `1px solid ${C.amber}`,
        borderRadius: 10, padding: "12px 16px", marginBottom: 24,
        display: "flex", alignItems: "center", gap: 10, textAlign: "right"
      }}>
        <span style={{ fontSize: 20 }}>⚠️</span>
        <div style={{ fontSize: 13, color: C.amber }}>
          عند حفظ الشروط، سيُطلب من جميع المستخدمين الموافقة عليها مجدداً عند تسجيل الدخول.
        </div>
      </div>

      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 20 }}>

        {/* Vendor Terms */}
        <div style={{
          background: C.surface, border: `1px solid ${C.border}`,
          borderRadius: 14, padding: 24,
        }}>
          <div style={{
            display: "flex", alignItems: "center", gap: 10,
            marginBottom: 16, justifyContent: "flex-end"
          }}>
            <div>
              <div style={{ fontSize: 16, fontWeight: 700, color: C.textPri, textAlign: "right" }}>
                🏪 شروط البائعين
              </div>
              <div style={{ fontSize: 12, color: C.textSec, textAlign: "right" }}>
                تُعرض على البائع عند تسجيل الدخول
              </div>
            </div>
            <div style={{
              width: 44, height: 44, borderRadius: 22,
              background: C.primaryDim, display: "flex",
              alignItems: "center", justifyContent: "center", fontSize: 22,
            }}>🏪</div>
          </div>

          <textarea
            value={vendorTerms}
            onChange={e => setVendorTerms(e.target.value)}
            placeholder="اكتب شروط وأحكام البائعين هنا..."
            style={{
              width: "100%", minHeight: 400,
              padding: "12px", background: C.surfaceHi,
              border: `1px solid ${C.border}`, borderRadius: 10,
              color: C.textPri, fontSize: 13, lineHeight: 1.8,
              textAlign: "right", direction: "rtl",
              resize: "vertical", boxSizing: "border-box",
              fontFamily: "'Tajawal', sans-serif",
            }}
          />
          <div style={{ fontSize: 11, color: C.textMuted, marginTop: 6, textAlign: "right" }}>
            {vendorTerms.length} حرف
          </div>
        </div>

        {/* Driver Terms */}
        <div style={{
          background: C.surface, border: `1px solid ${C.border}`,
          borderRadius: 14, padding: 24,
        }}>
          <div style={{
            display: "flex", alignItems: "center", gap: 10,
            marginBottom: 16, justifyContent: "flex-end"
          }}>
            <div>
              <div style={{ fontSize: 16, fontWeight: 700, color: C.textPri, textAlign: "right" }}>
                🚗 شروط السائقين
              </div>
              <div style={{ fontSize: 12, color: C.textSec, textAlign: "right" }}>
                تُعرض على السائق عند تسجيل الدخول
              </div>
            </div>
            <div style={{
              width: 44, height: 44, borderRadius: 22,
              background: C.orangeDim, display: "flex",
              alignItems: "center", justifyContent: "center", fontSize: 22,
            }}>🚗</div>
          </div>

          <textarea
            value={driverTerms}
            onChange={e => setDriverTerms(e.target.value)}
            placeholder="اكتب شروط وأحكام السائقين هنا..."
            style={{
              width: "100%", minHeight: 400,
              padding: "12px", background: C.surfaceHi,
              border: `1px solid ${C.border}`, borderRadius: 10,
              color: C.textPri, fontSize: 13, lineHeight: 1.8,
              textAlign: "right", direction: "rtl",
              resize: "vertical", boxSizing: "border-box",
              fontFamily: "'Tajawal', sans-serif",
            }}
          />
          <div style={{ fontSize: 11, color: C.textMuted, marginTop: 6, textAlign: "right" }}>
            {driverTerms.length} حرف
          </div>
        </div>
      </div>

      {/* Save Button */}
      <div style={{ marginTop: 24, display: "flex", justifyContent: "flex-end" }}>
        <button
          onClick={save}
          style={{
            padding: "12px 40px", background: saved ? C.green : C.primary,
            border: "none", borderRadius: 10, color: "white",
            fontWeight: 700, fontSize: 15, cursor: "pointer",
            transition: "background 0.3s",
          }}
        >
          {saved ? '✅ تم الحفظ! سيُطلب من المستخدمين الموافقة مجدداً' : 'حفظ الشروط والأحكام'}
        </button>
      </div>
    </div>
  );
};

const LiveTracker = ({ orderId }) => {
  const C = useTheme();
  const [location, setLocation] = useState(null);
  const [status, setStatus] = useState(null);
  useEffect(() => {
    if (!window.Echo || !orderId) return;
    const ch = window.Echo.private(`order.${orderId}`);
    ch.listen('.location.updated', d => setLocation({ lat: d.lat, lng: d.lng }));
    ch.listen('.status.changed', d => setStatus(d.message));
    return () => window.Echo.leave(`order.${orderId}`);
  }, [orderId]);
  return (
    <div style={{ background: C.surfaceHi, borderRadius: 12, padding: 16, border: `1px solid ${C.border}`, textAlign: "right" }}>
      <div style={{ fontSize: 13, fontWeight: 700, color: C.textPri, marginBottom: 10 }}>🗺 التتبع المباشر</div>
      {location ? <div style={{ fontSize: 13, color: C.textSec, lineHeight: 2 }}><div>خط العرض: {location.lat} 📍</div><div>خط الطول: {location.lng} 📍</div></div>
        : <div style={{ fontSize: 13, color: C.textMuted }}>في انتظار موقع السائق...</div>}
      {status && <div style={{ marginTop: 10, padding: "8px 12px", background: C.primaryDim, borderRadius: 8, fontSize: 13, color: C.primary }}>{status}</div>}
    </div>
  );
};

const OrdersPage = () => {
  const C = useTheme();
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState(null);
  useEffect(() => {
    fetch('/api/admin/orders', { headers: { 'Authorization': `Bearer ${localStorage.getItem('admin_token')}`, 'Accept': 'application/json' } })
      .then(r => r.json()).then(d => { setOrders(d.data || []); setLoading(false); }).catch(() => setLoading(false));
  }, []);
  if (loading) return <div style={{ color: C.textSec, padding: 40, textAlign: "center" }}>جاري التحميل...</div>;
  return (
    <div>
      <div style={{ marginBottom: 28 }}>
        <div style={{ fontSize: 24, fontWeight: 800, color: C.textPri }}>جميع الطلبات</div>
        <div style={{ fontSize: 14, color: C.textSec, marginTop: 4 }}>رؤية كاملة لجميع عمليات التوصيل</div>
      </div>
      <div style={{ display: "grid", gridTemplateColumns: selected ? "1fr 360px" : "1fr", gap: 20 }}>
        <div style={{ background: C.surface, border: `1px solid ${C.border}`, borderRadius: 14, padding: "20px" }}>
          {orders.length === 0 && <div style={{ color: C.textSec, textAlign: "center", padding: 40 }}>لا توجد طلبات</div>}
          <table style={{ width: "100%", borderCollapse: "collapse" }}>
            <thead><tr>{["رقم الطلب","المنتج","البائع","السائق","المبلغ","التاريخ","الحالة","الإجراءات"].map(h => <Th key={h}>{h}</Th>)}</tr></thead>
            <tbody>
              {orders.map(o => (
                <tr key={o.id}>
                  <Td><span style={{ color: C.primary, fontFamily: "monospace", fontWeight: 700 }}>WSL-{o.id}</span></Td>
                  <Td>{o.product_name}</Td>
                  <Td><span style={{ color: C.textSec }}>{o.vendor?.name}</span></Td>
                  <Td><span style={{ color: C.textSec }}>{o.delivery?.driver?.name || '—'}</span></Td>
                  <Td><span style={{ fontWeight: 700 }}>SDG {o.delivery?.total_charged || '—'}</span></Td>
                  <Td><span style={{ color: C.textSec, fontSize: 12 }}>{new Date(o.created_at).toLocaleDateString()}</span></Td>
                  <Td><Badge label={o.status.charAt(0).toUpperCase()+o.status.slice(1)} type={o.status} /></Td>
                  <Td><ActionBtn label="تفاصيل" color={C.blue} bg={C.blueBg} onClick={() => setSelected(o)} /></Td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {selected && (
          <div style={{ background: C.surface, border: `1px solid ${C.border}`, borderRadius: 14, padding: "20px" }}>
            <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 20 }}>
              <button onClick={() => setSelected(null)} style={{ background: "none", border: "none", color: C.textMuted, cursor: "pointer", fontSize: 18 }}>✕</button>
              <div style={{ fontSize: 16, fontWeight: 700, color: C.textPri }}>WSL-{selected.id}</div>
            </div>
            <div style={{ background: C.surfaceHi, borderRadius: 10, padding: "14px", marginBottom: 12, border: `1px solid ${C.border}`, textAlign: "right" }}>
              <div style={{ fontSize: 11, color: C.textSec, marginBottom: 6 }}>المنتج</div>
              <div style={{ fontSize: 14, fontWeight: 600, color: C.textPri }}>{selected.product_name}</div>
              <div style={{ fontSize: 12, color: C.textSec, marginTop: 4 }}>📍 {selected.pickup_address}</div>
              <div style={{ fontSize: 12, color: C.textSec }}>🏁 {selected.dropoff_address}</div>
            </div>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10, marginBottom: 12 }}>
              <div style={{ background: C.surfaceHi, borderRadius: 10, padding: "12px", border: `1px solid ${C.border}`, textAlign: "right" }}>
                <div style={{ fontSize: 11, color: C.orange, fontWeight: 700, marginBottom: 4 }}>السائق 🚗</div>
                <div style={{ fontSize: 13, fontWeight: 600, color: C.textPri }}>{selected.delivery?.driver?.name || '—'}</div>
              </div>
              <div style={{ background: C.surfaceHi, borderRadius: 10, padding: "12px", border: `1px solid ${C.border}`, textAlign: "right" }}>
                <div style={{ fontSize: 11, color: C.primary, fontWeight: 700, marginBottom: 4 }}>البائع 🏪</div>
                <div style={{ fontSize: 13, fontWeight: 600, color: C.textPri }}>{selected.vendor?.name}</div>
              </div>
            </div>
            <div style={{ marginBottom: 12, display: "flex", alignItems: "center", gap: 10, justifyContent: "flex-end" }}>
              {selected.delivery?.total_charged && <span style={{ fontSize: 15, fontWeight: 800, color: C.primary }}>SDG {selected.delivery.total_charged}</span>}
              <Badge label={selected.status.charAt(0).toUpperCase()+selected.status.slice(1)} type={selected.status} />
            </div>
            {selected.status === 'active' && <LiveTracker orderId={selected.id} />}
          </div>
        )}
      </div>
    </div>
  );
};

// ── NAV ──────────────────────────────────────────────────────────
const NAV = [
  { key: "dashboard",   label: "لوحة التحكم", icon: "⊞", section: "main" },
  { key: "topups",      label: "الشحن",        icon: "💳", section: "actions" },
  { key: "withdrawals", label: "السحوبات",     icon: "🏦", section: "actions" },
  { key: "disputes",    label: "النزاعات",     icon: "⚠",  section: "actions" },
  { key: "orders",      label: "الطلبات",      icon: "📦", section: "data" },
  { key: "vendors",     label: "البائعون",     icon: "🏪", section: "data" },
  { key: "drivers",     label: "السائقون",     icon: "🚗", section: "data" },
  { key: "settings", label: "الإعدادات", icon: "⚙️", section: "data" },
  { key: "terms",    label: "الشروط والسياسات", icon: "📄", section: "data" },
];

// ── ROOT ─────────────────────────────────────────────────────────
export default function WasilAdmin({ onLogout }) {
  const [page, setPage] = useState("dashboard");
  const [dark, setDark] = useState(() => localStorage.getItem('wasil_theme') !== 'light');
  const [badges, setBadges] = useState({ topups: 0, withdrawals: 0, disputes: 0 });

  useEffect(() => {
    fetch('/api/admin/dashboard', {
      headers: { 'Authorization': `Bearer ${localStorage.getItem('admin_token')}`, 'Accept': 'application/json' }
    }).then(r => r.json()).then(d => {
      setBadges({
        topups:      d.stats?.pending_topups      ?? 0,
        withdrawals: d.stats?.pending_withdrawals ?? 0,
        disputes:    d.stats?.pending_disputes    ?? 0,
      });
    });
  }, []);

  const C = dark ? DARK : LIGHT;
  const toggleTheme = () => { const n = !dark; setDark(n); localStorage.setItem('wasil_theme', n ? 'dark' : 'light'); };
  const renderPage = () => {
    switch (page) {
      case "dashboard":   return <DashboardPage setPage={setPage} />;
      case "topups":      return <TopupsPage />;
      case "withdrawals": return <WithdrawalsPage />;
      case "disputes":    return <DisputesPage />;
      case "orders":      return <OrdersPage />;
      case "vendors":     return <UsersPage type="vendors" />;
      case "drivers":     return <UsersPage type="drivers" />;
      case "settings":    return <SettingsPage />;
      case "terms":       return <TermsPage />;
      default:            return <DashboardPage setPage={setPage} />;
    }
  };

  return (
    <ThemeContext.Provider value={dark ? DARK : LIGHT}>
      <style>{`* { font-family: 'Tajawal', sans-serif !important; }`}</style>
      <div style={{ display: "flex", minHeight: "100vh", background: C.bg, fontFamily: "'Tajawal', sans-serif", color: C.textPri, direction: "rtl", transition: "background 0.3s" }}>
        <aside style={{ width: 230, background: C.surface, borderLeft: `1px solid ${C.border}`, display: "flex", flexDirection: "column", position: "sticky", top: 0, height: "100vh" }}>
          <div style={{ padding: "20px", borderBottom: `1px solid ${C.border}` }}>
            <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
              <button onClick={toggleTheme} style={{ background: C.surfaceHi, border: `1px solid ${C.border}`, borderRadius: 8, width: 30, height: 30, display: "flex", alignItems: "center", justifyContent: "center", cursor: "pointer", fontSize: 14 }}>{dark ? "☀️" : "🌙"}</button>
              <div style={{ flex: 1, textAlign: "right" }}>
                <div style={{ fontSize: 16, fontWeight: 800, color: C.primary }}>وصل | wsil</div>
                <div style={{ fontSize: 12, color: C.textMuted }}>لوحة الإدارة</div>
              </div>
              <div style={{ width: 36, height: 36, borderRadius: 10, background: `linear-gradient(135deg, ${C.primary}, ${C.primaryLight})`, display: "flex", alignItems: "center", justifyContent: "center", fontWeight: 900, fontSize: 18, color: C.white }}>و</div>
            </div>
          </div>
          <nav style={{ flex: 1, padding: "16px 10px", overflowY: "auto" }}>
            {["main", "actions", "data"].map(section => (
              <div key={section} style={{ marginBottom: 8 }}>
                {section !== "main" && (
                  <div style={{ fontSize: 12, color: C.textMuted, padding: "8px 10px 4px", textAlign: "right" }}>
                    {section === "actions" ? "الإجراءات المعلقة" : "البيانات"}
                  </div>
                )}
                {NAV.filter(n => n.section === section).map(n => (
                  <button key={n.key} onClick={() => setPage(n.key)} style={{
                    width: "100%", display: "flex", alignItems: "center", gap: 10, flexDirection: "row-reverse",
                    padding: "9px 10px", borderRadius: 8, border: "none", cursor: "pointer",
                    background: page === n.key ? C.primaryDim : "transparent",
                    borderRight: page === n.key ? `3px solid ${C.primary}` : "3px solid transparent",
                    color: page === n.key ? C.primary : C.textSec,
                    fontSize: 14, fontWeight: page === n.key ? 700 : 400, marginBottom: 2,
                  }}>
                    <span style={{ fontSize: 15 }}>{n.icon}</span>
                    <span style={{ flex: 1, textAlign: "right" }}>{n.label}</span>
                    {badges[n.key] > 0 && page !== n.key && (
                      <span style={{ background: C.orange, color: C.white, borderRadius: "50%", width: 18, height: 18, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 10, fontWeight: 700 }}>{badges[n.key]}</span>
                    )}
                  </button>
                ))}
              </div>
            ))}
          </nav>
          <div style={{ padding: "16px 20px", borderTop: `1px solid ${C.border}`, display: "flex", alignItems: "center", gap: 10, flexDirection: "row-reverse" }}>
            <div style={{ width: 32, height: 32, borderRadius: "50%", background: `linear-gradient(135deg, ${C.primary}, ${C.primaryLight})`, display: "flex", alignItems: "center", justifyContent: "center", fontWeight: 700, color: C.white, fontSize: 13 }}>م</div>
            <div style={{ flex: 1, textAlign: "right" }}>
              <div style={{ fontSize: 12, fontWeight: 600, color: C.textPri }}>المسؤول</div>
              <div style={{ fontSize: 10, color: C.textMuted }}>مسؤول النظام</div>
            </div>
            <span onClick={onLogout} style={{ color: C.textMuted, cursor: "pointer", fontSize: 14 }} title="تسجيل الخروج">↩</span>
          </div>
        </aside>
        <main style={{ flex: 1, padding: "32px", overflowY: "auto" }}>{renderPage()}</main>
      </div>
    </ThemeContext.Provider>
  );
}