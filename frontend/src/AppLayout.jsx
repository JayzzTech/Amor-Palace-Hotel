import { Link, Navigate, useLocation } from "react-router-dom";
import { useEffect, useState } from "react";
import { getSession } from "./api.js";

export function AppLayout({ children }) {
    const [session, setSession] = useState(null);
    const [sessionStatus, setSessionStatus] = useState("loading");
    const [menuOpen, setMenuOpen] = useState(false);
    const location = useLocation();
    useEffect(() => {
        let current = true;
        setSessionStatus("loading");
        getSession()
            .then((result) => {
                if (!current) return;
                setSession(result.data);
                setSessionStatus("ready");
            })
            .catch(() => {
                if (!current) return;
                setSession(null);
                setSessionStatus("error");
            });
        return () => { current = false; };
    }, [location.pathname]);
    useEffect(() => { setMenuOpen(false); }, [location.pathname]);
    useEffect(() => {
        if (sessionStatus !== "ready" || location.pathname !== "/" || location.hash !== "#branches") return;
        const frame = window.requestAnimationFrame(() => {
            document.getElementById("branches")?.scrollIntoView({
                behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth",
                block: "start",
            });
        });
        return () => window.cancelAnimationFrame(frame);
    }, [location.hash, location.pathname, sessionStatus]);

    const role = session?.role;
    const managementRole = ["ROLE_STAFF", "ROLE_ADMIN", "ROLE_SUPER_ADMIN"].includes(role);
    const dashboardPath = role === "ROLE_SUPER_ADMIN" ? "/super" : "/staff";
    const rolePath = location.pathname === "/super" || location.pathname === "/staff";
    const guestOnlyPath = ["/account", "/payment"].some((path) => location.pathname.startsWith(path)) || location.pathname.startsWith("/book/");
    const restrictedToManagement = managementRole && !rolePath;
    const incorrectManagementPage =
        (role === "ROLE_SUPER_ADMIN" && location.pathname === "/staff") ||
        (role !== "ROLE_SUPER_ADMIN" && location.pathname === "/super");
    const guestOnManagementPage = !managementRole && rolePath;
    const signedOutOnPrivatePage = !session && ["/account", "/staff", "/super", "/payment"].some((path) => location.pathname.startsWith(path));

    if (sessionStatus === "loading") {
        return <main className="page"><p>Checking your account…</p></main>;
    }

    if (sessionStatus === "error") {
        return <main className="page"><p className="alert-error" role="alert">We could not verify your account. Please refresh the page and try again.</p></main>;
    }

    if (restrictedToManagement) return <Navigate to={dashboardPath} replace />;
    if (incorrectManagementPage) return <Navigate to={dashboardPath} replace />;
    if (guestOnManagementPage) return <Navigate to={session ? "/account" : "/login"} replace />;
    if (signedOutOnPrivatePage) return <Navigate to="/login" replace />;
    if (guestOnlyPath && managementRole) return <Navigate to={dashboardPath} replace />;

    return (
        <>
            <header className="site-header">
                <div className="wrap">
                    <Link className="brand" to={managementRole ? dashboardPath : "/"}>
                        <span className="brand-mark" aria-hidden="true">AP</span>
                        <span>Amor Palace <small>Hotel</small></span>
                    </Link>
                    <button className="nav-toggle" type="button" aria-expanded={menuOpen} aria-controls="main-navigation" onClick={() => setMenuOpen((open) => !open)}>
                        {menuOpen ? "Close" : "Menu"}
                    </button>
                    <nav className={`nav${menuOpen ? " is-open" : ""}`} id="main-navigation" aria-label="Main navigation">
                        {managementRole ? (
                            <>
                                <Link to={dashboardPath}>{role === "ROLE_SUPER_ADMIN" ? "Super admin" : role === "ROLE_ADMIN" ? "Admin dashboard" : "Front desk"}</Link>
                                <a className="btn btn-ghost btn-sm" href="/logout">Sign out</a>
                            </>
                        ) : (
                            <>
                                <Link to="/">Home</Link>
                                <Link to="/#branches">Hotels</Link>
                                <Link to="/about">About</Link>
                                <Link to="/contact">Contact</Link>
                                {session ? <><Link to="/account">My stays</Link><a className="btn btn-ghost btn-sm" href="/logout">Sign out</a></> : <><Link to="/login">Login</Link><Link className="btn btn-sm" to="/#branches">Book now</Link></>}
                            </>
                        )}
                    </nav>
                </div>
            </header>
            <main>{children}</main>
            {!managementRole && <footer className="site-footer" id="contact">
                <div className="wrap site-footer-grid">
                    <div>
                        <Link className="brand" to="/">Amor Palace Hotel</Link>
                        <p>Rest, beautifully kept across Pampanga.</p>
                    </div>
                    <nav aria-label="Footer navigation">
                        <span>Explore</span>
                        <Link to="/#branches">Hotels</Link>
                        <Link to="/about">About Amor Palace</Link>
                        <Link to="/account">My stays</Link>
                    </nav>
                    <div className="site-footer-contact">
                        <span>Get in touch</span>
                        <Link to="/contact">Contact & directions</Link>
                    </div>
                </div>
                <div className="wrap site-footer-bottom">© 2026 Amor Palace Hotel. All rights reserved.</div>
            </footer>}
        </>
    );
}
