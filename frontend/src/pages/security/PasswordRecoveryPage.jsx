import { useState } from "react";
import { getCsrfToken, postJson } from "../../api.js";

export function PasswordRecoveryPage() {
    const [email, setEmail] = useState("");
    const [token, setToken] = useState("");
    const [password, setPassword] = useState("");
    const [message, setMessage] = useState("");
    const [error, setError] = useState("");
    const csrf = async () => (await getCsrfToken("app")).token;
    const requestReset = async (event) => {
        event.preventDefault();
        setError("");
        try {
            const result = await postJson("/api/password/forgot", { email, _token: await csrf() });
            setMessage(result.data.message);
            if (result.data.developmentToken) setToken(result.data.developmentToken);
        } catch (requestError) {
            setError(requestError.message);
        }
    };
    const resetPassword = async (event) => {
        event.preventDefault();
        setError("");
        try {
            const result = await postJson("/api/password/reset", { token, password, _token: await csrf() });
            setMessage(result.data.message);
        } catch (requestError) {
            setError(requestError.message);
        }
    };
    return <section className="auth-page"><div className="auth-card card"><span className="auth-kicker">Account recovery</span><h1>Reset your password</h1>{message && <p className="success">{message}</p>}{error && <p className="error">{error}</p>}
        {!token ? <form onSubmit={requestReset}><label>Email<input type="email" value={email} onChange={(event) => setEmail(event.target.value)} required /></label><button className="btn" type="submit">Send reset link</button></form> : <form onSubmit={resetPassword}><label>Reset token<input value={token} onChange={(event) => setToken(event.target.value)} required /></label><label>New password<input type="password" minLength="8" value={password} onChange={(event) => setPassword(event.target.value)} required /></label><button className="btn" type="submit">Change password</button></form>}
    </div></section>;
}
