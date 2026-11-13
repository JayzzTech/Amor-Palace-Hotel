import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { getCsrfToken, getReservation, postJson } from "../../api.js";

export function PaymentPage() {
    const { reservationId } = useParams();
    const navigate = useNavigate();
    const [token, setToken] = useState("");
    const [method, setMethod] = useState("instapay");
    const [error, setError] = useState("");
    const [saving, setSaving] = useState(false);
    const [reservation, setReservation] = useState(null);
    useEffect(() => {
        getCsrfToken("app").then((result) => setToken(result.token));
        getReservation(reservationId).then((result) => setReservation(result.data)).catch(() => setReservation(false));
    }, []);
    const submit = async (event) => {
        event.preventDefault();
        setSaving(true);
        setError("");
        try {
            await postJson(`/api/reservations/${reservationId}/pay`, {
                _token: token,
                paymentMethod: method,
            });
            navigate("/account");
        } catch (requestError) {
            setError(requestError.message);
        } finally {
            setSaving(false);
        }
    };
    return (
        <section className="section payment-page">
            <div className="wrap payment-layout">
                <div className="panel payment-panel">
                    <span className="auth-kicker">Secure payment</span>
                    <h1>Complete your payment</h1>
                    <p>Your room is held while payment is completed. Scan the QR code or choose a payment method, then confirm payment to finalize reservation <strong>{reservation?.reference}</strong>.</p>
                    {error && <p className="error">{error}</p>}
                    <div
                        className="payment-qr"
                        role="img"
                        aria-label="Payment QR code"
                    >
                        <div className="qr-marker top-left" />
                        <div className="qr-marker top-right" />
                        <div className="qr-marker bottom-left" />
                        <div className="qr-data" />
                    </div>
                    {reservation && <p className="payment-amount"><span>Amount due</span><strong>₱{reservation.total.toLocaleString()}</strong></p>}
                    <form
                        className="panel payment-method-panel"
                        onSubmit={submit}
                    >
                        <p className="eyebrow dark">Payment method</p>
                        <h2>How would you like to pay?</h2>
                        <label className="payment-method"><input type="radio" name="payment" value="credit-card" checked={method === "credit-card"} onChange={(event) => setMethod(event.target.value)} /><span><b>Credit / Debit Card</b><small>Visa, Mastercard and other cards</small></span></label>
                        <label className="payment-method"><input type="radio" name="payment" value="instapay" checked={method === "instapay"} onChange={(event) => setMethod(event.target.value)} /><span><b>InstaPay</b><small>Pay securely through your bank app</small></span></label>
                        <button
                            className="btn"
                            type="submit"
                            disabled={!token || saving}
                        >
                            {saving
                                ? "Confirming payment…"
                                : "Pay and confirm reservation"}
                        </button>
                    </form>
                </div>
            </div>
        </section>
    );
}
