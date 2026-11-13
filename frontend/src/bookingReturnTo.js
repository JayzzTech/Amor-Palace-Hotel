const bookingReturnPattern = /^\/book\/\d+(?:\?checkIn=\d{4}-\d{2}-\d{2}&checkOut=\d{4}-\d{2}-\d{2}&guests=(?:[1-9]|10))?$/;

export function getBookingReturnTo(value) {
    return typeof value === "string" && bookingReturnPattern.test(value) ? value : null;
}
