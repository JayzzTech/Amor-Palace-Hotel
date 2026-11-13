import { useEffect, useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { getCsrfToken, loginAccount } from "../../api.js";
import { getBookingReturnTo } from "../../bookingReturnTo.js";

export function LoginPage() {
    const [token, setToken] = useState("");
    const [form, setForm] = useState({ email: "", password: "" });
    const [error, setError] = useState("");
    const [csrfError, setCsrfError] = useState("");
    const navigate = useNavigate();
    const location = useLocation();
    const returnTo = getBookingReturnTo(new URLSearchParams(location.search).get("returnTo"));
    useEffect(() => {
        let current = true;
        getCsrfToken("authenticate")
            .then((result) => {
                if (current) setToken(result.token);
            })
            .catch(() => {
                if (current) setCsrfError("Secure sign-in could not be started. Refresh this page and try again.");
            });
        return () => { current = false; };
    }, []);
    function update(event) {
        setForm((current) => ({
            ...current,
            [event.target.name]: event.target.value,
        }));
    }

    async function submit(event) {
        event.preventDefault();
        setError("");
        try {
            const result = await loginAccount({ ...form, _token: token });
            const target = returnTo && result.data.target === "/account"
                ? returnTo
                : result.data.target;
            navigate(target, { replace: true });
        } catch (requestError) {
            setError(requestError.message);
        }
    }

    return (
        <div className="auth-page">
            <div className="auth-card card">
                <span className="auth-kicker">Amor Palace</span>
                <h1>Welcome back</h1>
                {csrfError && <p className="error" role="alert">{csrfError}</p>}
                {error && <p className="error">{error}</p>}
                <form onSubmit={submit}>
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
                            Password
                            <input
                                name="password"
                                value={form.password}
                                onChange={update}
                                type="password"
                                required
                            />
                        </label>
                    </div>
                    <button className="btn" type="submit" disabled={!token || Boolean(csrfError)}>
                        Sign in
                    </button>
                </form>
                <p className="small">
                    <Link to="/forgot-password">Forgot your password?</Link>
                </p>
                <p className="small">
                    New to Amor Palace?{" "}
                    <Link to={returnTo ? `/register?returnTo=${encodeURIComponent(returnTo)}` : "/register"}>Create an account</Link>.
                </p>
            </div>
        </div>
    );
}
