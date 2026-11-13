// Runtime validation coverage for: booking return navigation
import { describe, expect, it } from "vitest";
import { getBookingReturnTo } from "../src/bookingReturnTo.js";

describe("booking return navigation", () => {
    it("preserves valid booking filters", () => {
        expect(getBookingReturnTo("/book/42?checkIn=2026-11-12&checkOut=2026-11-14&guests=3"))
            .toBe("/book/42?checkIn=2026-11-12&checkOut=2026-11-14&guests=3");
    });

    it("allows a booking route without filters", () => {
        expect(getBookingReturnTo("/book/42")).toBe("/book/42");
    });

    it("rejects external destinations and unexpected query parameters", () => {
        expect(getBookingReturnTo("https://example.com/book/42")).toBeNull();
        expect(getBookingReturnTo("/book/42?next=https://example.com")).toBeNull();
    });
});
