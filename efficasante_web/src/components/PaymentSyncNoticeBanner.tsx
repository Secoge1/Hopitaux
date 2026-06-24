import { useCallback, useEffect, useState } from 'react';
import { api, getStoredUser } from '../services/api';
import './PaymentSyncNoticeBanner.css';

type Notice = {
  key: string;
  enabled: boolean;
  stamp: string | null;
  title: string;
  message: string;
  duration_ms?: number;
};

function storageKey(userId: number): string {
  return `hopitaux_payment_sync_notice_${userId}`;
}

export default function PaymentSyncNoticeBanner() {
  const [notice, setNotice] = useState<Notice | null>(null);
  const [visible, setVisible] = useState(false);

  const dismiss = useCallback((userId: number, stamp: string) => {
    try {
      localStorage.setItem(storageKey(userId), stamp);
    } catch {
      /* ignore */
    }
    setVisible(false);
    window.setTimeout(() => setNotice(null), 700);
  }, []);

  useEffect(() => {
    let timer: number | undefined;
    let cancelled = false;

    (async () => {
      try {
        const res = await api.getTenantNotices();
        if (cancelled || !res.data?.notices?.length) return;

        const user = getStoredUser();
        const userId = user?.id ?? res.data.user_id;
        const item = res.data.notices.find((n) => n.key === 'payment_finance_sync' && n.enabled && n.stamp);
        if (!item?.stamp || !userId) return;

        const seen = localStorage.getItem(storageKey(userId));
        if (seen === item.stamp) return;

        setNotice(item);
        setVisible(true);
        const duration = item.duration_ms ?? 10000;
        timer = window.setTimeout(() => dismiss(userId, item.stamp!), duration);
      } catch {
        /* API indisponible ou feature off */
      }
    })();

    return () => {
      cancelled = true;
      if (timer) window.clearTimeout(timer);
    };
  }, [dismiss]);

  if (!notice) return null;

  const user = getStoredUser();
  const userId = user?.id;
  if (!userId || !notice.stamp) return null;

  return (
    <div
      className={`payment-sync-notice-banner ${visible ? 'is-visible' : 'is-hidden'}`}
      role="status"
      aria-live="polite"
    >
      <div className="payment-sync-notice-banner__icon" aria-hidden>
        ★
      </div>
      <div className="payment-sync-notice-banner__body">
        <strong>{notice.title}</strong>
        <p>{notice.message}</p>
      </div>
      <button
        type="button"
        className="payment-sync-notice-banner__close"
        aria-label="Fermer"
        onClick={() => dismiss(userId, notice.stamp!)}
      >
        ×
      </button>
    </div>
  );
}
