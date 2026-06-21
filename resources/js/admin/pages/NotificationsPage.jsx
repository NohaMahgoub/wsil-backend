import { useState, useEffect } from "react";
import { useContext } from "react";
import { ThemeContext } from "../../components/WasilAdmin";
const useTheme = () => useContext(ThemeContext);

const NotificationsPage = () => {
  const C = useTheme();
  const [target, setTarget] = useState('all');
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [searchQ, setSearchQ] = useState('');
  const [searchResults, setSearchResults] = useState([]);
  const [selectedUser, setSelectedUser] = useState(null);
  const [sending, setSending] = useState(false);
  const [result, setResult] = useState(null);

  useEffect(() => {
    if (target !== 'user' || searchQ.length < 2) {
      setSearchResults([]);
      return;
    }
    const t = setTimeout(() => {
      fetch(`/api/admin/notifications/search-users?q=${searchQ}`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('admin_token')}`,
          'Accept': 'application/json',
        }
      }).then(r => r.json()).then(d => setSearchResults(d.data || []));
    }, 400);
    return () => clearTimeout(t);
  }, [searchQ, target]);

  const send = async () => {
    if (!title || !body) return;
    if (target === 'user' && !selectedUser) return;

    const targetLabel = {
      all:     'الكل (بائعون + سائقون)',
      vendors: 'البائعون فقط',
      drivers: 'السائقون فقط',
      user:    selectedUser?.name ?? 'مستخدم محدد',
    }[target];

    const confirmed = window.confirm(
      `هل أنت متأكد من إرسال هذا الإشعار؟\n\n` +
      `العنوان: ${title}\n` +
      `النص: ${body}\n` +
      `إرسال إلى: ${targetLabel}`
    );
    if (!confirmed) return;

    setSending(true);
    setResult(null);
    const res = await fetch('/api/admin/notifications/send', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('admin_token')}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        target,
        title,
        body,
        user_id: selectedUser?.id ?? null,
      }),
    }).then(r => r.json());
    setSending(false);
    setResult(res);
    if (res.sent > 0) {
      setTitle('');
      setBody('');
      setSelectedUser(null);
      setSearchQ('');
    }
  };

  const targetOptions = [
    { value: 'all',     label: '👥 الكل (بائعون + سائقون)' },
    { value: 'vendors', label: '🏪 البائعون فقط' },
    { value: 'drivers', label: '🚗 السائقون فقط' },
    { value: 'user',    label: '👤 مستخدم محدد' },
  ];

  return (
    <div>
      <div style={{ marginBottom: 28 }}>
        <div style={{ fontSize: 24, fontWeight: 800, color: C.textPri }}>إرسال الإشعارات</div>
        <div style={{ fontSize: 14, color: C.textSec, marginTop: 4 }}>
          أرسل إشعارات مباشرة للمستخدمين
        </div>
      </div>

      <div style={{ maxWidth: 560, background: C.surface, border: `1px solid ${C.border}`, borderRadius: 14, padding: 24 }}>

        {/* Target */}
        <div style={{ marginBottom: 20 }}>
          <div style={{ fontSize: 13, color: C.textSec, marginBottom: 8, textAlign: "right" }}>إرسال إلى</div>
          <div style={{ display: "flex", gap: 8, flexWrap: "wrap", justifyContent: "flex-end" }}>
            {targetOptions.map(opt => (
              <button
                key={opt.value}
                onClick={() => { setTarget(opt.value); setSelectedUser(null); setSearchQ(''); }}
                style={{
                  padding: "8px 14px", borderRadius: 20, fontSize: 13, fontWeight: 600,
                  cursor: "pointer", border: `1.5px solid ${target === opt.value ? C.primary : C.border}`,
                  background: target === opt.value ? C.primaryDim : C.surfaceHi,
                  color: target === opt.value ? C.primary : C.textSec,
                }}
              >
                {opt.label}
              </button>
            ))}
          </div>
        </div>

        {/* User search */}
        {target === 'user' && (
          <div style={{ marginBottom: 20, textAlign: "right" }}>
            <div style={{ fontSize: 13, color: C.textSec, marginBottom: 6 }}>ابحث عن مستخدم</div>
            {selectedUser ? (
              <div style={{
                display: "flex", alignItems: "center", justifyContent: "space-between",
                padding: "10px 14px", background: C.primaryDim,
                border: `1px solid ${C.primary}`, borderRadius: 10,
              }}>
                <button onClick={() => setSelectedUser(null)} style={{ background: "none", border: "none", color: C.red, cursor: "pointer", fontSize: 16 }}>✕</button>
                <div style={{ textAlign: "right" }}>
                  <div style={{ fontSize: 13, fontWeight: 700, color: C.textPri }}>{selectedUser.name}</div>
                  <div style={{ fontSize: 11, color: C.textSec }}>{selectedUser.phone} · {selectedUser.role === 'vendor' ? '🏪 بائع' : '🚗 سائق'}</div>
                </div>
              </div>
            ) : (
              <div style={{ position: "relative" }}>
                <input
                  value={searchQ}
                  onChange={e => setSearchQ(e.target.value)}
                  placeholder="ابحث بالاسم أو رقم الهاتف..."
                  style={{
                    width: "100%", padding: "10px 12px", background: C.surfaceHi,
                    border: `1px solid ${C.border}`, borderRadius: 10,
                    color: C.textPri, fontSize: 13, textAlign: "right",
                    boxSizing: "border-box",
                  }}
                />
                {searchResults.length > 0 && (
                  <div style={{
                    position: "absolute", top: "100%", right: 0, left: 0, zIndex: 10,
                    background: C.surface, border: `1px solid ${C.border}`,
                    borderRadius: 10, marginTop: 4, overflow: "hidden",
                    boxShadow: "0 8px 24px rgba(0,0,0,0.12)",
                  }}>
                    {searchResults.map(u => (
                      <div
                        key={u.id}
                        onClick={() => { setSelectedUser(u); setSearchQ(''); setSearchResults([]); }}
                        style={{
                          padding: "10px 14px", cursor: "pointer", textAlign: "right",
                          borderBottom: `1px solid ${C.border}`,
                        }}
                        onMouseEnter={e => e.currentTarget.style.background = C.surfaceHi}
                        onMouseLeave={e => e.currentTarget.style.background = "transparent"}
                      >
                        <div style={{ fontSize: 13, fontWeight: 600, color: C.textPri }}>{u.name}</div>
                        <div style={{ fontSize: 11, color: C.textSec }}>{u.phone} · {u.role === 'vendor' ? '🏪 بائع' : '🚗 سائق'}</div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>
        )}

        {/* Title */}
        <div style={{ marginBottom: 16, textAlign: "right" }}>
          <div style={{ fontSize: 13, color: C.textSec, marginBottom: 6 }}>عنوان الإشعار</div>
          <input
            value={title}
            onChange={e => setTitle(e.target.value)}
            maxLength={100}
            placeholder="مثال: تحديث مهم 🔔"
            style={{
              width: "100%", padding: "10px 12px", background: C.surfaceHi,
              border: `1px solid ${C.border}`, borderRadius: 10,
              color: C.textPri, fontSize: 14, textAlign: "right",
              boxSizing: "border-box",
            }}
          />
          <div style={{ fontSize: 11, color: C.textMuted, marginTop: 4, textAlign: "left" }}>{title.length}/100</div>
        </div>

        {/* Body */}
        <div style={{ marginBottom: 24, textAlign: "right" }}>
          <div style={{ fontSize: 13, color: C.textSec, marginBottom: 6 }}>نص الإشعار</div>
          <textarea
            value={body}
            onChange={e => setBody(e.target.value)}
            maxLength={500}
            placeholder="اكتب محتوى الإشعار هنا..."
            rows={4}
            style={{
              width: "100%", padding: "10px 12px", background: C.surfaceHi,
              border: `1px solid ${C.border}`, borderRadius: 10,
              color: C.textPri, fontSize: 14, textAlign: "right",
              direction: "rtl", resize: "none", boxSizing: "border-box",
              fontFamily: "'Tajawal', sans-serif",
            }}
          />
          <div style={{ fontSize: 11, color: C.textMuted, marginTop: 4, textAlign: "left" }}>{body.length}/500</div>
        </div>

        {/* Result */}
        {result && (
          <div style={{
            marginBottom: 16, padding: "12px 16px", borderRadius: 10, textAlign: "right",
            background: result.sent > 0 ? C.greenBg : C.redBg,
            border: `1px solid ${result.sent > 0 ? C.green : C.red}`,
          }}>
            <div style={{ fontSize: 13, fontWeight: 700, color: result.sent > 0 ? C.green : C.red }}>
              {result.sent > 0 ? '✅ تم الإرسال!' : '❌ فشل الإرسال'}
            </div>
            <div style={{ fontSize: 12, color: C.textSec, marginTop: 4 }}>
              تم الإرسال: {result.sent} · فشل: {result.failed} · الإجمالي: {result.total}
            </div>
          </div>
        )}

        {/* Send Button */}
        <button
          onClick={send}
          disabled={sending || !title || !body || (target === 'user' && !selectedUser)}
          style={{
            width: "100%", padding: "13px", background: C.primary,
            border: "none", borderRadius: 10, color: "white",
            fontWeight: 700, fontSize: 15, cursor: "pointer",
            opacity: (sending || !title || !body || (target === 'user' && !selectedUser)) ? 0.5 : 1,
            fontFamily: "'Tajawal', sans-serif",
          }}
        >
          {sending ? 'جاري الإرسال...' : '🔔 إرسال الإشعار'}
        </button>
      </div>
    </div>
  );
};

export default NotificationsPage;