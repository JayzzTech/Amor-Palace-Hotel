const policies = {
    privacy: {
        title: "Privacy Policy",
        intro: "Amor Palace Hotel protects reservation, account, and guest information.",
        sections: [
            ["Information we collect", "We collect account details, reservation information, room preferences, contact details, and identification details required for lawful hotel check-in."],
            ["How information is used", "Information is used to manage reservations, provide hotel services, improve operations, communicate booking updates, and meet legal requirements."],
            ["Your choices", "Contact the hotel to request access, correction, or removal of information, subject to mandatory record-retention requirements."],
        ],
    },
    terms: {
        title: "Terms and Conditions",
        intro: "These terms apply to website reservations and stays at Amor Palace Hotel.",
        sections: [
            ["Reservations", "A reservation is confirmed only when recorded in the hotel's system and the applicable confirmation is issued."],
            ["Guest conduct", "Guests must follow hotel rules, respect other guests, and report room damage promptly."],
            ["Identification", "Valid identification may be required at check-in for every registered guest where applicable."],
        ],
    },
    cancellation: {
        title: "Cancellation Policy",
        intro: "Cancellation terms should be confirmed by the hotel before finalizing each stay.",
        sections: [
            ["Guest cancellation", "Pending reservations may be cancelled through My Stays. Confirmed bookings should be cancelled through the hotel's contact channels."],
            ["Hotel cancellation", "If the hotel cannot provide a reserved room, staff will assist with the earliest suitable alternative or another approved remedy."],
            ["No-show", "No-show handling is determined by branch policy and the reservation type."],
        ],
    },
};

export function PolicyPage({ kind }) {
    const policy = policies[kind] || policies.terms;
    return (
        <section className="policy-page section">
            <div className="wrap">
                <header className="policy-card">
                    <span className="eyebrow dark">Amor Palace Hotel</span>
                    <h1>{policy.title}</h1>
                    <p className="policy-intro">{policy.intro}</p>
                </header>
                <div className="policy-sections">
                    {policy.sections.map(([heading, text], index) => (
                        <article className="policy-section" key={heading}>
                            <span className="policy-section-number">{String(index + 1).padStart(2, "0")}</span>
                            <div>
                                <h2>{heading}</h2>
                                <p>{text}</p>
                            </div>
                        </article>
                    ))}
                </div>
            </div>
        </section>
    );
}
