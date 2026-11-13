import { useEffect, useState } from "react";
import { Link, useLocation, useParams } from "react-router-dom";
import { getRooms } from "../../api.js";
import { roomImages } from "../../roomImages.js";

const fallbackRoomImages = {
    1: roomImages["Amor Loco Hotel"],
    2: roomImages["Mi Amor Hotel"],
};

export function RoomsPage() {
    const { branchId } = useParams();
    const location = useLocation();
    const [rooms, setRooms] = useState([]);
    const [branch, setBranch] = useState(null);
    const [availability, setAvailability] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const bookingQuery = availability
        ? `?${new URLSearchParams({
            checkIn: availability.checkIn,
            checkOut: availability.checkOut,
            guests: String(availability.guests),
        })}`
        : "";
    useEffect(() => {
        let current = true;
        const params = new URLSearchParams(location.search);
        const query = {
            checkIn: params.get("checkIn") || undefined,
            checkOut: params.get("checkOut") || undefined,
            guests: params.get("guests") || undefined,
        };
        const refreshRooms = () => {
            getRooms(branchId, query)
                .then((result) => {
                    if (!current) return;
                    setRooms(result.data);
                    setBranch(result.branch);
                    setAvailability(result.availability);
                    setError("");
                })
                .catch(() => {
                    if (!current) return;
                    setRooms([]);
                    setBranch(null);
                    setError("This hotel is unavailable or its rooms could not be loaded.");
                })
                .finally(() => {
                    if (current) setLoading(false);
                });
        };
        setLoading(true);
        setError("");
        refreshRooms();
        const timer = window.setInterval(refreshRooms, 30000);
        window.addEventListener("focus", refreshRooms);
        return () => {
            current = false;
            window.clearInterval(timer);
            window.removeEventListener("focus", refreshRooms);
        };
    }, [branchId, location.search]);
    if (loading) return <section className="page"><p>Loading hotel rooms…</p></section>;
    if (error) return (
        <section className="page">
            <div className="panel no-room-results">
                <p className="eyebrow dark">Hotel information</p>
                <h1>Hotel unavailable</h1>
                <p>{error}</p>
                <Link className="btn" to="/#branches">Browse available hotels</Link>
            </div>
        </section>
    );
    return (
        <>
            <section className="page-head branch-hero">
                <div className="wrap branch-hero-content">
                    <Link className="breadcrumb" to="/#branches">
                        All hotels
                    </Link>
                    <p className="eyebrow">Amor Palace Collection</p>
                    <h1>{branch?.name || "Choose your room"}</h1>
                    <p>{branch?.description || "Find a room made for slowing down, with warm hospitality and thoughtful details."}</p>
                    <div className="branch-meta">{branch?.address && <a href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${branch.name}, ${branch.address}`)}`} target="_blank" rel="noreferrer"><span aria-hidden="true">⌖</span><span>{branch.address}<small>Search maps</small></span></a>}{branch?.phone && <a href={`tel:${branch.phone}`}><span aria-hidden="true">☎</span><span>{branch.phone}<small>Call hotel</small></span></a>}</div>
                </div>
            </section>
            <div className="wrap">
                <section className="branch-details-grid"><div className="branch-detail-card"><span className="panel-kicker">Reserve directly</span><div className="branch-rating-summary"><strong>Easy</strong><span>Choose your room<small>Review your dates and stay details before booking.</small></span></div></div><div className="branch-detail-card"><span className="panel-kicker">Arrival & departure</span><div className="branch-time-row"><span>Check-in</span><strong>2:00 PM</strong></div><div className="branch-time-row"><span>Check-out</span><strong>12:00 PM</strong></div></div><div className="branch-detail-card"><span className="panel-kicker">Important rules</span><ul><li>Valid ID required at check-in.</li><li>No smoking inside rooms.</li><li>Pets require approval.</li></ul></div></section>
                <div className="availability-heading room-selection-heading">
                    <div>
                        <p className="eyebrow dark">Available rooms</p>
                        <h2>Find your stay</h2>
                    </div>
                    <span className="stay-summary">
                        <strong>{rooms.length} available</strong>
                        {availability && <small>for {availability.checkIn} – {availability.checkOut} · {availability.guests} {availability.guests === 1 ? "guest" : "guests"} · live</small>}
                    </span>
                </div>
                <div className="cards branch-room-grid">
                    {rooms.map((room, index) => (
                        <article
                            className="card room-choice-card"
                            key={room.id}
                        >
                            <Link
                                className="room-choice-card-link"
                                to={`/book/${room.id}${bookingQuery}`}
                                aria-label={`Reserve ${room.type}, Room ${room.number}`}
                            />
                            <div
                                className={`visual room-photo room-photo-${index % 4}`}
                                style={
                                    (roomImages[branch?.name] || fallbackRoomImages[Number(branchId)])?.length
                                        ? {
                                              backgroundImage: `linear-gradient(180deg, rgba(27,20,15,.04), rgba(27,20,15,.72)), url("${(roomImages[branch?.name] || fallbackRoomImages[Number(branchId)])[index % (roomImages[branch?.name] || fallbackRoomImages[Number(branchId)]).length]}")`,
                                          }
                                        : undefined
                                }
                            >
                                <span>{room.type}</span>
                            </div>
                            <div className="card-body">
                                <p className="muted small">
                                    Room {room.number} · Sleeps {room.capacity}
                                </p>
                                <p>{room.description}</p>
                                <ul className="tags">
                                    {room.amenities.map((amenity) => (
                                        <li key={amenity}>{amenity}</li>
                                    ))}
                                </ul>
                                <div className="room-footer">
                                    <div>
                                        <span className="price">
                                            ₱
                                            {room.pricePerNight.toLocaleString()}
                                        </span>{" "}
                                        <span className="muted small">
                                            a night
                                        </span>
                                    </div>
                                    <Link
                                        className="btn"
                                        to={`/book/${room.id}${bookingQuery}`}
                                    >
                                        Reserve
                                    </Link>
                                </div>
                            </div>
                        </article>
                    ))}
                </div>
                {!rooms.length && (
                    <div className="panel no-room-results">
                        <h3>No rooms are currently available</h3>
                        <p>Try another hotel or check again later.</p>
                    </div>
                )}
            </div>
        </>
    );
}
