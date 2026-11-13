import { useEffect, useState } from "react";
import { getAccountDashboard, getCsrfToken, postJson } from "../../api.js";

export function AccountDashboardPage() {
    const [data, setData] = useState(null);
    const [token, setToken] = useState("");
    useEffect(() => {
        getAccountDashboard()
            .then((result) => setData(result.data))
            .catch(() => setData(false));
    }, []);
    useEffect(() => {
        getCsrfToken("app").then((result) => setToken(result.token));
    }, []);
    const cancelReservation = async (id) => {
        if (!window.confirm("Cancel this reservation?")) return;
        await postJson(`/api/reservations/${id}/cancel`, { _token: token });
        const result = await getAccountDashboard();
        setData(result.data);
    };
    if (data === false)
        return (
            <section className="page">
                <p className="error">
                    Please sign in to view your reservations.
                </p>
            </section>
        );
    if (!data)
        return (
            <section className="page">
                <p>Loading reservations…</p>
            </section>
        );
    return (
        <section className="guest-dashboard-page">
            <div className="wrap dashboard-page guest-dashboard">
                <div className="guest-dashboard-hero">
                    <div>
                        <p className="eyebrow dark">Guest portal</p>
                        <h1>Welcome back, {data.guest.split(" ")[0]}</h1>
                        <p>
                            Manage your stays and upcoming hotel reservations.
                        </p>
                    </div>
                    <a className="btn" href="/#branches">
                        Book a new stay
                    </a>
                </div>
                <div className="guest-stats">
                    <article className="guest-stat-card">
                        <span className="guest-stat-icon">⌂</span>
                        <div>
                            <strong>{data.stats.upcoming}</strong>
                            <span>Upcoming stays</span>
                        </div>
                    </article>
                    <article className="guest-stat-card">
                        <span className="guest-stat-icon">✓</span>
                        <div>
                            <strong>{data.stats.completed}</strong>
                            <span>Completed stays</span>
                        </div>
                    </article>
                    <article className="guest-stat-card">
                        <span className="guest-stat-icon">₱</span>
                        <div>
                            <strong>
                                ₱{data.stats.spent.toLocaleString()}
                            </strong>
                            <span>Total booked</span>
                        </div>
                    </article>
                </div>
                <section className="panel guest-bookings-panel">
                    <div className="guest-bookings-heading">
                        <div>
                            <p className="panel-kicker">My bookings</p>
                            <h2>Reservations</h2>
                        </div>
                    </div>
                    <div className="guest-bookings-list">
                        {data.reservations.map((reservation) => (
                            <article
                                className="guest-booking-card"
                                key={reservation.id}
                            >
                                <div className="guest-booking-main">
                                    <span className="guest-booking-reference">
                                        {reservation.reference}
                                    </span>
                                    <h3>{reservation.branch}</h3>
                                    <p>Room {reservation.room}</p>
                                </div>
                                <div className="guest-booking-date">
                                    <span>Stay dates</span>
                                    <strong>
                                        {new Date(
                                            reservation.checkIn,
                                        ).toLocaleDateString()}{" "}
                                        —{" "}
                                        {new Date(
                                            reservation.checkOut,
                                        ).toLocaleDateString()}
                                    </strong>
                                    <small>Reservation stay</small>
                                </div>
                                <div className="guest-booking-total">
                                    <span>Total</span>
                                    <strong>
                                        ₱{reservation.total.toLocaleString()}
                                    </strong>
                                </div>
                                <div className="guest-booking-action">
                                    <span
                                        className={`badge badge-${reservation.status}`}
                                    >
                                        {reservation.status.replace("_", " ")}
                                    </span>
                                    {["pending", "confirmed"].includes(
                                        reservation.status,
                                    ) && (
                                        <button
                                            className="cancel-link"
                                            onClick={() =>
                                                cancelReservation(
                                                    reservation.id,
                                                )
                                            }
                                        >
                                            Cancel reservation
                                        </button>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                </section>
            </div>
        </section>
    );
}
