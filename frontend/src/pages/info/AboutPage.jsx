import { Link } from "react-router-dom";

export function AboutPage() {
    return (
        <section className="section info-page">
            <div className="wrap">
                <header className="info-page-hero">
                    <p className="eyebrow dark">About Amor Palace</p>
                    <h1>A more considered way to stay.</h1>
                    <p className="info-page-lead">
                        Amor Palace brings together independent hotel stays in Pampanga with a simple way to explore rooms and make a reservation.
                    </p>
                    <Link className="btn" to="/#branches">Explore our hotels</Link>
                </header>
                <div className="info-story-grid">
                    <article className="info-story-card">
                        <span className="panel-kicker">Find your stay</span>
                        <h2>Choose what works for you.</h2>
                        <p>Compare available rooms, review the listed amenities and rates, then select the room that fits your visit.</p>
                    </article>
                    <article className="info-story-card">
                        <span className="panel-kicker">Before you arrive</span>
                        <h2>Know the details.</h2>
                        <p>Review your reservation and share requests when booking. Hotel staff can manage arrivals, room status, and housekeeping.</p>
                    </article>
                </div>
                <div className="info-page-cta">
                    <div>
                        <p className="eyebrow">Start planning</p>
                        <h2>Take a look around.</h2>
                    </div>
                    <Link className="btn btn-ghost" to="/contact">Contact & directions</Link>
                </div>
            </div>
        </section>
    );
}
