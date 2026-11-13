import { useEffect, useMemo, useState } from "react";
import { useLocation, useNavigate, useParams } from "react-router-dom";
import { createReservation, getCsrfToken, getRoom, getSession } from "../../api.js";
import { roomImages } from "../../roomImages.js";

const bookingRoomImages = {
    1: roomImages["Amor Loco Hotel"],
    2: roomImages["Mi Amor Hotel"],
};

const extraPrices = { towel: 150, toothbrush: 50, slippers: 100, water: 80, pillow: 200 };
const extraLabels = { towel: "Extra towel", toothbrush: "Extra toothbrush", slippers: "Extra slippers", water: "Bottled water", pillow: "Extra pillow" };

function nextDate(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return "";
    const date = new Date(`${value}T00:00:00Z`);
    if (Number.isNaN(date.getTime()) || date.toISOString().slice(0, 10) !== value) return "";
    date.setUTCDate(date.getUTCDate() + 1);
    return date.toISOString().slice(0, 10);
}

export function BookingPage() {
    const { roomId } = useParams();
    const navigate = useNavigate();
    const location = useLocation();
    const bookingParams = new URLSearchParams(location.search);
    const requestedGuests = Number(bookingParams.get("guests") || 1);
    const [room, setRoom] = useState(null);
    const [access, setAccess] = useState("checking");
    const [token, setToken] = useState("");
    const [error, setError] = useState("");
    const [saving, setSaving] = useState(false);
    const [showRules, setShowRules] = useState(false);
    const [activePhoto, setActivePhoto] = useState(0);
    const [form, setForm] = useState({ stayType: "long", checkIn: bookingParams.get("checkIn") ? `${bookingParams.get("checkIn")}T14:00` : "", checkOut: bookingParams.get("checkOut") || "", shortHours: 3, additionalGuests: Math.max(0, Math.min(9, requestedGuests - 1)), discountType: "none", notes: "", rulesAccepted: false });
    const [extras, setExtras] = useState({ towel: 0, toothbrush: 0, slippers: 0, water: 0, pillow: 0 });

    useEffect(() => {
        let current = true;
        getSession().then((result) => {
            if (!current) return;
            if (result.data) {
                setAccess("authorized");
                return;
            }
            const returnTo = `${location.pathname}${location.search}`;
            navigate(`/login?returnTo=${encodeURIComponent(returnTo)}`, { replace: true });
        }).catch(() => {
            if (current) setAccess("error");
        });
        return () => { current = false; };
    }, [location.pathname, location.search, navigate]);
    useEffect(() => {
        if (access !== "authorized") return;
        getRoom(roomId).then((result) => setRoom(result.data)).catch(() => setRoom(false));
        getCsrfToken("app").then((result) => setToken(result.token)).catch(() => setError("Could not start a secure reservation. Please refresh the page."));
    }, [access, roomId]);
    const update = (event) => {
        const { name, value, type, checked } = event.target;
        setForm((current) => {
            const nextForm = { ...current, [name]: type === "checkbox" ? checked : value };
            const checkoutMin = name === "checkIn" ? nextDate(value.slice(0, 10)) : "";
            if (checkoutMin && current.checkOut < checkoutMin) nextForm.checkOut = checkoutMin;
            return nextForm;
        });
    };
    const nights = useMemo(() => { if (form.stayType === "short") return 0; if (!form.checkIn || !form.checkOut) return 0; return Math.max(0, Math.ceil((new Date(form.checkOut) - new Date(form.checkIn.slice(0, 10))) / 86400000)); }, [form.checkIn, form.checkOut, form.stayType]);
    const getShortStayRate = (room, hours) => {
        const branchName = room?.branchName || "";
        const roomType = String(room?.type || "").toLowerCase();
        if (branchName.includes("Amor Loco") && roomType.includes("tryke")) {
            return { 2: 290, 3: 330, 6: 600, 12: 1100, 24: 1850 }[hours];
        }
        if (branchName.includes("Amor Loco") && roomType.includes("standard")) {
            return { 3: 400, 6: 660, 12: 1250, 24: 1950 }[hours];
        }
        if (branchName.includes("Mi Amor") && (roomType.includes("tryke") || roomType.includes("trike"))) {
            return { 3: 300, 6: 550, 12: 1200, 24: 1700 }[hours];
        }
        if (branchName.includes("Mi Amor") && (roomType.includes("executive") || roomType.includes("standard"))) {
            return { 3: 450, 6: 850, 12: 1500, 24: 2000 }[hours];
        }
        return undefined;
    };
    const roomSubtotal = form.stayType === "short"
        ? (getShortStayRate(room, Number(form.shortHours)) ?? Math.ceil((room?.pricePerNight || 0) / 24 * Number(form.shortHours)))
        : (room?.pricePerNight || 0) * nights;
    const personsTotal = Number(form.additionalGuests) * 500;
    const extrasTotal = Object.entries(extras).reduce((sum, [key, quantity]) => sum + extraPrices[key] * quantity, 0);
    const discountTotal = form.discountType === "none" ? 0 : Math.round((roomSubtotal + personsTotal + extrasTotal) * .2);
    const total = Math.max(0, roomSubtotal + personsTotal + extrasTotal - discountTotal);
    const submit = async (event) => { event.preventDefault(); if (!form.rulesAccepted) { setShowRules(true); return; } setError(""); setSaving(true); try { await createReservation(room.id, { ...form, additionalGuests: Number(form.additionalGuests), shortHours: Number(form.shortHours), extras, _token: token }); navigate("/account"); } catch (requestError) { setError(requestError.message); } finally { setSaving(false); } };
    if (access === "checking") return <section className="page"><p>Checking your account…</p></section>;
    if (access === "error") return <section className="page"><p className="alert-error" role="alert">We could not verify your sign-in. Please refresh and try again.</p></section>;
    if (room === false) return <section className="page"><p className="alert-error" role="alert">Room details could not be loaded. It may no longer be available.</p><button className="btn btn-ghost" type="button" onClick={() => navigate("/#branches")}>Browse hotels</button></section>;
    if (!room) return <section className="page"><p>Loading room details…</p></section>;
    const photos = roomImages[room.branchName] || bookingRoomImages[Number(room.branchId)] || [];
    const galleryPhoto = photos[activePhoto % photos.length];
    return <section className="section booking-page"><div className="wrap two-col booking-layout"><div className="panel booking-form-panel"><button className="booking-back-button" type="button" onClick={() => navigate(`/branches/${room.branchId}`)}>← Back to rooms</button><div className="booking-page-heading"><div><span className="auth-kicker">Secure your stay</span><h1>Reserve your room</h1></div><span className="booking-room-type">{room.type}</span></div>{error && <div className="alert-error">{error}</div>}
        {photos.length > 0 && <section className="room-detail-gallery" aria-label={`${room.type} photo gallery`}><div className="room-detail-main-photo" style={{ backgroundImage: `linear-gradient(180deg, rgba(27,20,15,.02), rgba(27,20,15,.18)), url("${galleryPhoto}")` }} /><div className="room-detail-thumbnails">{photos.map((photo, index) => <button type="button" key={photo} className={activePhoto === index ? "is-active" : ""} onClick={() => setActivePhoto(index)} aria-label={`Show room photo ${index + 1}`}><img src={photo} alt="" /></button>)}</div></section>}
        <form onSubmit={submit}>
        <fieldset className="stay-type-picker"><legend>Stay type</legend><label className="stay-type-option"><input name="stayType" type="radio" value="short" checked={form.stayType === "short"} onChange={update} /><span><b>Short Stay</b><small>Pay for hours</small></span></label><label className="stay-type-option"><input name="stayType" type="radio" value="long" checked={form.stayType === "long"} onChange={update} /><span><b>Long Stay</b><small>Choose dates</small></span></label></fieldset>
        <div className="grid-form stay-grid"><div className="field"><label>Check-in<input name="checkIn" type="datetime-local" value={form.checkIn} onChange={update} required /></label></div>{form.stayType === "long" ? <div className="field"><label>Check-out<input name="checkOut" type="date" min={nextDate(form.checkIn.slice(0, 10))} value={form.checkOut} onChange={update} required /></label></div> : <div className="field"><label>Short stay duration<select name="shortHours" value={form.shortHours} onChange={update}>{room.branchName?.includes("Amor Loco") ? room.type?.toLowerCase().includes("tryke") ? <><option value="2">2 hours</option><option value="3">3 hours</option><option value="6">6 hours</option><option value="12">12 hours</option><option value="24">24 hours</option></> : <><option value="3">3 hours</option><option value="6">6 hours</option><option value="12">12 hours</option><option value="24">24 hours</option></> : room.branchName?.includes("Mi Amor") ? <><option value="3">3 hours</option><option value="6">6 hours</option><option value="12">12 hours</option><option value="24">24 hours</option></> : <><option value="3">3 hours</option><option value="6">6 hours</option><option value="9">9 hours</option><option value="12">12 hours</option><option value="24">24 hours</option></>}</select></label></div>}<div className="field"><label>Additional guests<input name="additionalGuests" type="number" min="0" max={Math.max(0, room.capacity - 1)} value={form.additionalGuests} onChange={update} /></label></div></div>
        <fieldset className="discount-picker"><legend>Discount</legend><label><input type="radio" name="discountType" value="none" checked={form.discountType === "none"} onChange={update} /> Regular</label><label><input type="radio" name="discountType" value="pwd" checked={form.discountType === "pwd"} onChange={update} /> PWD (20%)</label><label><input type="radio" name="discountType" value="senior" checked={form.discountType === "senior"} onChange={update} /> Senior Citizen (20%)</label></fieldset>
        <section className="booking-extra-section"><div className="booking-extra-heading"><div><span className="panel-kicker">Guests & extras</span><h3>Add to your stay</h3></div><span className="small muted">Maximum {room.capacity} guests in this room</span></div><div className="extra-person-card"><div><b>Additional person</b><p className="small muted">₱500 per person</p></div><div className="quantity-control"><button type="button" onClick={() => setForm((current) => ({ ...current, additionalGuests: Math.max(0, Number(current.additionalGuests) - 1) }))}>−</button><input value={form.additionalGuests} readOnly /><button type="button" onClick={() => setForm((current) => ({ ...current, additionalGuests: Math.min(room.capacity - 1, Number(current.additionalGuests) + 1) }))}>+</button></div></div><div className="extras-list">{Object.entries(extraPrices).map(([key, price]) => <div className="extra-option" key={key}><label><input type="checkbox" checked={extras[key] > 0} onChange={(event) => setExtras((current) => ({ ...current, [key]: event.target.checked ? 1 : 0 }))} /><span><b>{extraLabels[key]}</b><small>₱{price.toLocaleString()}</small></span></label><div className="quantity-control compact"><button type="button" onClick={() => setExtras((current) => ({ ...current, [key]: Math.max(0, current[key] - 1) }))}>−</button><input value={extras[key]} readOnly /><button type="button" onClick={() => setExtras((current) => ({ ...current, [key]: Math.min(10, current[key] + 1) }))}>+</button></div></div>)}</div></section>
        <div className="field"><label>Requests for the hotel (optional)<textarea name="notes" rows="3" value={form.notes} onChange={update} placeholder="Late arrival, extra pillows, celebrating something special" /></label></div><label className="consent-field"><input name="rulesAccepted" type="checkbox" checked={form.rulesAccepted} onChange={update} /><span>I have read and accept the Rules and Regulations.</span></label><button className="btn" type="submit" disabled={!token || saving}>{saving ? "Creating reservation…" : "Confirm reservation"}</button></form></div>
        <aside className="card booking-receipt"><div className="card-body booking-receipt-body"><div className="booking-receipt-heading"><div><span className="panel-kicker">Your reservation</span><h2>Booking summary</h2></div><span className="receipt-status">Pay at hotel</span></div><h3>{room.branchName}</h3><p className="muted small">Room {room.number}, sleeps {room.capacity}</p><p>{room.description}</p><table><tbody><tr><td>Room</td><td>{form.stayType === "short" ? `${form.shortHours} hours` : `${nights} ${nights === 1 ? "night" : "nights"}`}</td></tr><tr><td>Room subtotal</td><td>₱{roomSubtotal.toLocaleString()}</td></tr><tr><td>Additional guests</td><td>₱{personsTotal.toLocaleString()}</td></tr><tr><td>Extras</td><td>₱{extrasTotal.toLocaleString()}</td></tr><tr><td>Discount</td><td>− ₱{discountTotal.toLocaleString()}</td></tr><tr><td><b>Subtotal</b></td><td><span className="price">₱{total.toLocaleString()}</span></td></tr></tbody></table></div></aside></div><dialog className="rules-dialog" open={showRules} onClose={() => setShowRules(false)}><div className="rules-dialog-content"><span className="auth-kicker">Before you continue</span><h2>Rules and Regulations</h2><ul><li>Valid identification and discount ID must be presented during check-in.</li><li>Check-in is 2:00 PM and check-out is 12:00 PM.</li><li>No smoking is allowed inside rooms.</li><li>Guests are responsible for room damage.</li></ul><label className="rules-accept"><input type="checkbox" checked={form.rulesAccepted} onChange={update} name="rulesAccepted" /> I have read and accept the Rules and Regulations.</label><div className="dialog-actions"><button className="btn btn-ghost" type="button" onClick={() => setShowRules(false)}>Cancel</button><button className="btn" type="button" disabled={!form.rulesAccepted} onClick={() => setShowRules(false)}>Accept & continue</button></div></div></dialog></section>;
}
