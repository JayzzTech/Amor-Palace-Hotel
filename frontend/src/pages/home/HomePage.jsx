import { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { getBranches } from "../../api.js";

function dateOffset(days) {
    const date = new Date();
    date.setDate(date.getDate() + days);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}

export function HomePage() {
    const [branches, setBranches] = useState([]);
    const [error, setError] = useState("");
    const [slide, setSlide] = useState(0);
    const [destination, setDestination] = useState("");
    const [checkIn, setCheckIn] = useState(() => dateOffset(1));
    const [checkOut, setCheckOut] = useState(() => dateOffset(2));
    const [guests, setGuests] = useState("2");
    const navigate = useNavigate();
    useEffect(() => {
        let current = true;
        const availability = { checkIn, checkOut, guests };
        const refreshAvailability = () => {
            getBranches(availability)
                .then((result) => {
                    if (!current) return;
                    setBranches(result.data);
                    setError("");
                })
                .catch(() => {
                    if (current) setError("Live availability could not be refreshed. Please try again.");
                });
        };
        refreshAvailability();
        const timer = window.setInterval(refreshAvailability, 30000);
        window.addEventListener("focus", refreshAvailability);
        return () => {
            current = false;
            window.clearInterval(timer);
            window.removeEventListener("focus", refreshAvailability);
        };
    }, [checkIn, checkOut, guests]);
    const findRoom = (event) => {
        event.preventDefault();
        if (destination) {
            const query = new URLSearchParams({ checkIn, checkOut, guests });
            navigate(`/branches/${destination}?${query}`);
            return;
        }
        document.getElementById("branches")?.scrollIntoView({ behavior: "smooth" });
    };
    const bookingUrl = (branchId) => `/branches/${branchId}?${new URLSearchParams({ checkIn, checkOut, guests })}`;
    useEffect(() => {
        if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return undefined;
        const timer = window.setInterval(
            () => setSlide((current) => (current + 1) % 3),
            5500,
        );
        return () => window.clearInterval(timer);
    }, []);
    return (
        <>
            <section className="hero home-hero-carousel" data-home-carousel>
                {["", "hero-slide-two", "hero-slide-three"].map(
                    (name, index) => (
                        <div
                            key={name || "first"}
                            className={`hero-backdrop hero-carousel-slide ${name} ${slide === index ? "is-active" : ""}`}
                            aria-hidden={slide !== index}
                        />
                    ),
                )}
                <div
                    className="hero-carousel-progress"
                    aria-label="Homepage slides"
                >
                    {[0, 1, 2].map((index) => (
                        <button
                            key={index}
                            className={`hero-carousel-dot ${slide === index ? "is-active" : ""}`}
                            type="button"
                            onClick={() => setSlide(index)}
                            aria-label={`Show slide ${index + 1}`}
                        />
                    ))}
                </div>
                <div className="wrap hero-content">
                    <p className="hero-kicker">Pampanga · The Amor Palace Collection</p>
                    <h1>
                        Stay close.<br /><span>Feel at home.</span>
                    </h1>
                    <p>
                        Thoughtful stays across Pampanga, each with its own
                        character, warm hospitality, and a room made for slowing
                        down.
                    </p>
                    <div className="hero-actions">
                        <a className="btn" href="#branches">
                            Explore our hotels
                        </a>
                        <a className="hero-text-link" href="#collection">
                            Discover the collection →
                        </a>
                    </div>
                </div>
            </section>
            <form className="wrap stay-search-panel" onSubmit={findRoom}>
                <label>
                    <span>Destination</span>
                    <select value={destination} onChange={(event) => setDestination(event.target.value)}>
                        <option value="" disabled>Choose a hotel</option>
                        {branches.map((branch) => <option key={branch.id} value={branch.id}>{branch.name}</option>)}
                    </select>
                </label>
                <label>
                    <span>Check-in</span>
                    <input type="date" value={checkIn} min={dateOffset(0)} required onChange={(event) => {
                        const nextCheckIn = event.target.value;
                        setCheckIn(nextCheckIn);
                        if (nextCheckIn >= checkOut) {
                            const nextDay = new Date(`${nextCheckIn}T00:00:00`);
                            nextDay.setDate(nextDay.getDate() + 1);
                            setCheckOut(`${nextDay.getFullYear()}-${String(nextDay.getMonth() + 1).padStart(2, "0")}-${String(nextDay.getDate()).padStart(2, "0")}`);
                        }
                    }} />
                </label>
                <label>
                    <span>Check-out</span>
                    <input type="date" value={checkOut} min={checkIn} required onChange={(event) => setCheckOut(event.target.value)} />
                </label>
                <label>
                    <span>Guests</span>
                    <select value={guests} onChange={(event) => setGuests(event.target.value)}>
                        <option value="1">1 guest</option>
                        <option value="2">2 adults</option>
                        <option value="3">3 guests</option>
                        <option value="4">4 guests</option>
                    </select>
                </label>
                <button className="btn stay-search-submit" type="submit">Find a room</button>
            </form>
            <section
                className="collection-note"
                id="highlights"
                aria-label="Hotel collection highlights"
            >
                <div className="wrap collection-note-grid">
                    <div>
                        <strong>{branches.length || "-"}</strong>
                        <span>Distinctive hotels</span>
                    </div>
                    <div>
                        <strong>1</strong>
                        <span>Seamless booking experience</span>
                    </div>
                    <div>
                        <strong>{branches.reduce((total, branch) => total + branch.availableRooms, 0)}</strong>
                        <span>Rooms available for your dates</span>
                    </div>
                </div>
            </section>
            <section className="section" id="branches">
                <div className="wrap">
                    <div className="branch-selector-heading">
                        <div>
                            <p className="eyebrow dark">
                                Choose your destination
                            </p>
                            <h2>Where would you like to stay?</h2>
                        </div>
                        <p className="muted">
                        Live room availability · refreshed every 30 seconds
                        </p>
                    </div>
                    {error && <p className="alert-error">{error}</p>}
                    <div className="cards branch-selector-grid">
                        {branches.map((branch, index) => (
                            <Link
                                className="card branch-card"
                                to={bookingUrl(branch.id)}
                                key={branch.id}
                            >
                                <div
                                    className={`visual branch-card-visual v${index % 3}`}
                                >
                                    <span>{branch.city}</span>
                                    {branch.availableRooms > 0 && (
                                        <span className="branch-availability">
                                            {branch.availableRooms} {branch.availableRooms === 1 ? "room" : "rooms"} available
                                        </span>
                                    )}
                                </div>
                                <div className="card-body branch-card-body">
                                    <div className="branch-card-copy">
                                        <h3>{branch.name}</h3>
                                        <p className="branch-tagline">
                                            <strong>{branch.tagline}</strong>
                                        </p>
                                        <p className="muted small branch-address">
                                            {branch.address}
                                        </p>
                                    </div>
                                    <div className="branch-card-footer">
                                        <div className="branch-price">
                                            {branch.minPrice !== null && branch.availableRooms > 0 ? (
                                                <>
                                                    <span>From</span>
                                                    <strong className="price">
                                                        ₱
                                                        {branch.minPrice.toLocaleString()}
                                                    </strong>
                                                    <span>per night</span>
                                                </>
                                            ) : <span>No rooms for these dates</span>}
                                        </div>
                                        <span className="btn btn-sm branch-card-action">
                                            {branch.availableRooms > 0 ? "View available rooms" : "See hotel details"}
                                        </span>
                                    </div>
                                </div>
                            </Link>
                        ))}
                        {!branches.length && !error && <p>Checking live availability…</p>}
                    </div>
                </div>
            </section>
            <section className="home-experience-section" id="collection">
                <div className="wrap home-experience-grid">
                    <div className="home-experience-copy">
                        <p className="eyebrow dark">Made for easy stays</p>
                        <h2>Comfort in every detail.</h2>
                        <p>Thoughtful rooms, attentive service, and straightforward booking across the Amor Palace collection.</p>
                        <a className="btn btn-ghost" href="#branches">Explore rooms</a>
                    </div>
                    <div className="home-feature-grid">
                        <article><span>Help</span><h3>Guest support</h3><p>Contact the hotel team before or during your stay.</p></article>
                        <article><span>01</span><h3>Comfortable rooms</h3><p>Clean, private spaces for work or rest.</p></article>
                        <article><span>02</span><h3>Prime locations</h3><p>Convenient stays across Pampanga.</p></article>
                        <article><span>03</span><h3>Simple booking</h3><p>Choose a room and confirm your stay.</p></article>
                    </div>
                </div>
            </section>
            <section className="home-story-section">
                <div className="wrap home-story-grid">
                    <div className="home-story-quote">
                        <p className="eyebrow dark">Designed around your stay</p>
                        <blockquote>
                            A clear booking experience, room details in one place, and useful information before arrival.
                        </blockquote>
                    </div>
                    <div className="home-story-stat">
                        <strong>Direct</strong>
                        <span>online reservation</span>
                    </div>
                    <div className="home-story-stat">
                        <strong>Live</strong>
                        <span>room availability</span>
                    </div>
                </div>
            </section>
        </>
    );
}
