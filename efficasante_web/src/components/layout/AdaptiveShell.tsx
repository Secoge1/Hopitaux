import React from 'react';
import { Outlet } from 'react-router-dom';
import { useIsMobile } from '../../hooks/useBreakpoint';
import DesktopLayout from './DesktopLayout';
import MobileLayout from './MobileLayout';
import PaymentSyncNoticeBanner from '../PaymentSyncNoticeBanner';

export default function AdaptiveShell() {
  const isMobile = useIsMobile();
  const Layout = isMobile ? MobileLayout : DesktopLayout;

  return (
    <div className={`app-shell ${isMobile ? 'mobile' : 'desktop'}`}>
      <Layout>
        <>
          <PaymentSyncNoticeBanner />
          <Outlet />
        </>
      </Layout>
    </div>
  );
}
