import { Navigate, Route, Routes } from 'react-router-dom';
import { AppLayout } from './AppLayout.jsx';
import { HomePage } from './pages/home/HomePage.jsx';
import { RoomsPage } from './pages/home/BranchRoomsPage.jsx';
import { BookingPage } from './pages/reservation/ReservationBookPage.jsx';
import { LoginPage } from './pages/security/LoginPage.jsx';
import { RegisterPage } from './pages/security/RegisterPage.jsx';
import { PaymentPage } from './pages/reservation/ReservationPaymentPage.jsx';
import { AccountDashboardPage } from './pages/account/AccountDashboardPage.jsx';
import { StaffDashboardPage } from './pages/staff/StaffDashboardPage.jsx';
import { SuperAdminDashboardPage } from './pages/super/SuperAdminDashboardPage.jsx';
import { PasswordRecoveryPage } from './pages/security/PasswordRecoveryPage.jsx';
import { PolicyPage } from './pages/legal/PolicyPage.jsx';
import { AboutPage } from './pages/info/AboutPage.jsx';
import { ContactPage } from './pages/info/ContactPage.jsx';

function AccessDeniedPage() {
  return <section className="auth-page"><div className="auth-card card"><span className="auth-kicker">Amor Palace Hotel</span><h1>Access denied</h1><p>You do not have permission to view this page.</p><a className="btn" href="/">Back to homepage</a></div></section>;
}

export default function App() {
  return <AppLayout><Routes>
    <Route path="/" element={<HomePage />} />
    <Route path="/hotels" element={<Navigate to="/#branches" replace />} />
    <Route path="/about" element={<AboutPage />} />
    <Route path="/contact" element={<ContactPage />} />
    <Route path="/branches/:branchId" element={<RoomsPage />} />
    <Route path="/book/:roomId" element={<BookingPage />} />
    <Route path="/payment/:reservationId" element={<PaymentPage />} />
    <Route path="/login" element={<LoginPage />} />
    <Route path="/forgot-password" element={<PasswordRecoveryPage />} />
    <Route path="/reset-password" element={<PasswordRecoveryPage />} />
    <Route path="/privacy" element={<PolicyPage kind="privacy" />} />
    <Route path="/terms" element={<PolicyPage kind="terms" />} />
    <Route path="/cancellation-policy" element={<PolicyPage kind="cancellation" />} />
    <Route path="/register" element={<RegisterPage />} />
    <Route path="/account" element={<AccountDashboardPage />} />
    <Route path="/staff" element={<StaffDashboardPage />} />
    <Route path="/super" element={<SuperAdminDashboardPage />} />
    <Route path="/access-denied" element={<AccessDeniedPage />} />
    <Route path="*" element={<HomePage />} />
  </Routes></AppLayout>;
}
