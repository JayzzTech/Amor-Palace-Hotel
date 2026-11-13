import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { getBranches } from "../../api.js";

export function ContactPage() {
    const [branches, setBranches] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        getBranches()
            .then((result) => setBranches(result.data))
            .catch(() => setError("Hotel locations could not be loaded right now. Please try again."))
            .finally(() => setLoading(false));
    }, []);

    return (
        <section className="section info-page contact-page">
            <div className="wrap">
                <header className="info-page-hero">
                    <p className="eyebrow dark">Contact & directions</p>
                    <h1>We’re here to help you find us.</h1>
                    <p className="info-page-lead">
                        Search for a hotel on the map or browse its available rooms.
                    </p>
                </header>
                {error && <p className="alert-error" role="alert">{error}</p>}
                <div className="contact-location-grid">
                    {branches.map((branch) => (
                        <article className="contact-location-card" key={branch.id}>
                            <span className="panel-kicker">{branch.city}</span>
                            <h2>{branch.name}</h2>
                            <p>{branch.address}</p>
                            <a
                                className="btn btn-ghost"
                                href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${branch.name}, ${branch.address}`)}`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                Search on Google Maps
                            </a>
                            <Link className="contact-hotel-link" to={`/branches/${branch.id}`}>View rooms & availability</Link>
                        </article>
                    ))}
                    {loading && <p>Loading hotel locations…</p>}
                    {!loading && !branches.length && !error && <p>No hotel locations are currently available.</p>}
                </div>
                <p className="contact-note">
                    Phone, email, and precise street addresses are not shown until verified hotel contact details are available.
                </p>
            </div>
        </section>
    );
}
