import { useEffect, useState } from "react";
import { Link, useLocation } from "react-router-dom";
import { getCsrfToken, registerAccount } from "../../api.js";
import { getBookingReturnTo } from "../../bookingReturnTo.js";

export function RegisterPage() {
    const location = useLocation();
    const returnTo = getBookingReturnTo(new URLSearchParams(location.search).get("returnTo")) || "";
    const [token, setToken] = useState("");
    const [form, setForm] = useState({
        name: "",
        email: "",
        phone: "",
        password: "",
        passwordConfirm: "",
        terms: false,
    });
    const [error, setError] = useState("");
    const [success, setSuccess] = useState("");
    useEffect(() => {
        getCsrfToken("app").then((result) => setToken(result.token));
    }, []);
    function update(event) {
        const { name, value, type, checked } = event.target;
        setForm((current) => ({
            ...current,
            [name]: type === "checkbox" ? checked : value,
        }));
    }

    async function submit(event) {
        event.preventDefault();
        setError("");
        setSuccess("");
        try {
            const result = await registerAccount({ ...form, _token: token });
            setSuccess(result.data.message);
            setForm({
                name: "",
                email: "",
                phone: "",
                password: "",
                passwordConfirm: "",
                terms: false,
            });
        } catch (requestError) {
            setError(requestError.message);
        }
    }

    return (
        <div className="auth-page">
            <div className="auth-card card">
                <span className="auth-kicker">Join Amor Palace</span>
                <h1>Create your account</h1>
                {error && <p className="error">{error}</p>}
                {success && (
                    <p className="success">
                        {success} <Link to={returnTo ? `/login?returnTo=${encodeURIComponent(returnTo)}` : "/login"}>Sign in</Link>.
                    </p>
                )}
                <form onSubmit={submit}>
                    <div className="field">
                        <label>
                            Full name
                            <input
                                name="name"
                                value={form.name}
                                onChange={update}
                                required
                            />
                        </label>
                    </div>
                    <div className="field">
                        <label>
                            Email
                            <input
                                name="email"
                                value={form.email}
                                onChange={update}
                                type="email"
                                required
                            />
                        </label>
                    </div>
                    <div className="field">
                        <label>
                            Mobile number
                            <input
                                name="phone"
                                value={form.phone}
                                onChange={update}
                                type="tel"
                            />
                        </label>
                    </div>
                    <div className="field">
                        <label>
                            Password
                            <input
                                name="password"
                                value={form.password}
                                onChange={update}
                                type="password"
                                minLength="8"
                                required
                            />
                        </label>
                    </div>
                    <div className="field">
                        <label>
                            Confirm password
                            <input
                                name="passwordConfirm"
                                value={form.passwordConfirm}
                                onChange={update}
                                type="password"
                                minLength="8"
                                required
                            />
                        </label>
                    </div>
                    <label className="consent-field">
                        <input
                            name="terms"
                            checked={form.terms}
                            onChange={update}
                            type="checkbox"
                            required
                        />{" "}
                        I agree to the <button className="legal-link" type="button" onClick={() => document.getElementById("terms-dialog")?.showModal()}>Terms</button> and <button className="legal-link" type="button" onClick={() => document.getElementById("privacy-dialog")?.showModal()}>Privacy Policy</button>.
                    </label>
                    <button className="btn" type="submit" disabled={!token}>
                        Create account
                    </button>
                </form>
                <p className="small">
                    Already registered? <Link to={returnTo ? `/login?returnTo=${encodeURIComponent(returnTo)}` : "/login"}>Sign in</Link>.
                </p>
            </div>
            <dialog className="legal-dialog" id="terms-dialog"><div className="legal-dialog-content"><div className="legal-dialog-heading"><span className="auth-kicker">Amor Palace Hotel</span><button className="legal-dialog-close" type="button" onClick={() => document.getElementById("terms-dialog")?.close()}>×</button></div><h2>Terms of Service</h2><p>By creating an account and making a reservation, you agree to provide accurate information and follow the hotel rules.</p><h3>Reservations and payment</h3><p>A reservation is held while payment is completed. Your stay is confirmed only after successful payment.</p><button className="btn" type="button" onClick={() => document.getElementById("terms-dialog")?.close()}>Close</button></div></dialog>
            <dialog className="legal-dialog" id="privacy-dialog"><div className="legal-dialog-content"><div className="legal-dialog-heading"><span className="auth-kicker">Amor Palace Hotel</span><button className="legal-dialog-close" type="button" onClick={() => document.getElementById("privacy-dialog")?.close()}>×</button></div><h2>Privacy Policy</h2><p>We use your information to create your account, manage reservations, contact you about your stay, and provide hotel support.</p><h3>Information we collect</h3><p>This may include your name, email address, phone number, reservation details, and hotel requests.</p><button className="btn" type="button" onClick={() => document.getElementById("privacy-dialog")?.close()}>Close</button></div></dialog>
        </div>
    );
}
