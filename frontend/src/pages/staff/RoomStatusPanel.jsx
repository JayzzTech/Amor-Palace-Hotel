import { useEffect, useState } from "react";
import { patchJson } from "../../api.js";

const roomStatuses = ["clean", "dirty", "cleaning", "occupied", "inspection", "maintenance"];

export function RoomStatusPanel({ rooms, token, onSaved, id = "staff-housekeeping", showBranch = false, branchNames = [] }) {
    const [selections, setSelections] = useState({});
    const [savingRoom, setSavingRoom] = useState(null);
    const [feedback, setFeedback] = useState({});
    const [selectedBranch, setSelectedBranch] = useState("");
    const roomsByBranch = showBranch
        ? Object.entries(rooms.reduce((groups, room) => {
            const branch = room.branch || "Unassigned branch";
            (groups[branch] ||= []).push(room);
            return groups;
        }, Object.fromEntries(branchNames.map((branch) => [branch, []]))))
            .map(([branch, branchRooms]) => [
                branch,
                branchRooms.sort((first, second) => String(first.number).localeCompare(String(second.number), undefined, { numeric: true })),
            ])
            .sort(([first], [second]) => first.localeCompare(second))
        : [];
    useEffect(() => {
        if (showBranch && roomsByBranch.length && !roomsByBranch.some(([branch]) => branch === selectedBranch)) {
            setSelectedBranch(roomsByBranch[0][0]);
        }
    }, [roomsByBranch, selectedBranch, showBranch]);
    const selectedBranchRooms = roomsByBranch.find(([branch]) => branch === selectedBranch)?.[1] || [];

    async function saveStatus(room) {
        const status = selections[room.id] || room.status;
        setSavingRoom(room.id);
        setFeedback((current) => ({ ...current, [room.id]: null }));
        try {
            await patchJson(`/api/staff/rooms/${room.id}/housekeeping`, {
                status,
                notes: room.notes || "",
                _token: token,
            });
            setSelections((current) => ({ ...current, [room.id]: status }));
            setFeedback((current) => ({ ...current, [room.id]: { type: "success", text: "Room status updated." } }));
            try {
                await onSaved();
            } catch (error) {
                setFeedback((current) => ({ ...current, [room.id]: { type: "error", text: `Status saved, but the room list could not refresh: ${error.message}` } }));
            }
        } catch (error) {
            setFeedback((current) => ({ ...current, [room.id]: { type: "error", text: error.message } }));
        } finally {
            setSavingRoom(null);
        }
    }

    return (
        <section className="panel dashboard-panel" id={id}>
            <div className="panel-heading">
                <div><span className="panel-kicker">Housekeeping</span><h3>Room status</h3></div>
                <span className="live-count">{rooms.length} rooms</span>
            </div>
            {showBranch ? (
                <div className="room-status-branches">
                    {selectedBranch && <section className="room-status-branch" aria-label={`${selectedBranch} room statuses`}>
                        <div className="room-status-branch-heading">
                            <div><span className="panel-kicker">Branch housekeeping</span><h4>{selectedBranch}</h4></div>
                            <div className="room-status-branch-picker">
                                <label className="sr-only" htmlFor={`${id}-branch`}>Choose a branch</label>
                                <select
                                    id={`${id}-branch`}
                                    value={selectedBranch}
                                    onChange={(event) => setSelectedBranch(event.target.value)}
                                    disabled={!roomsByBranch.length}
                                >
                                    {!roomsByBranch.length && <option value="">No branches with rooms</option>}
                                    {roomsByBranch.map(([branch]) => <option value={branch} key={branch}>{branch}</option>)}
                                </select>
                            </div>
                            <span className="live-count">{selectedBranchRooms.length} {selectedBranchRooms.length === 1 ? "room" : "rooms"}</span>
                        </div>
                        <div className="table-wrap">
                            <table>
                                <thead><tr><th>Room</th><th>Status</th><th>Action</th></tr></thead>
                                <tbody>{selectedBranchRooms.length ? selectedBranchRooms.map(renderRoomRow) : <tr><td colSpan="3">No rooms are registered for this branch.</td></tr>}</tbody>
                            </table>
                        </div>
                    </section>}
                    {!rooms.length && <p className="room-status-empty">No rooms are available to update.</p>}
                </div>
            ) : (
                <div className="table-wrap">
                    <table>
                        <thead><tr><th>Room</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>{rooms.length ? rooms.map(renderRoomRow) : <tr><td colSpan="3">No rooms are available to update.</td></tr>}</tbody>
                    </table>
                </div>
            )}
        </section>
    );

    function renderRoomRow(room) {
        const roomFeedback = feedback[room.id];
        return (
            <tr key={room.id}>
                <td><b>{room.number}</b>{room.type && <span className="muted small room-status-type">{room.type}</span>}</td>
                <td>
                    <label className="sr-only" htmlFor={`room-status-${id}-${room.id}`}>Status for room {room.number}</label>
                    <select
                        id={`room-status-${id}-${room.id}`}
                        value={selections[room.id] || room.status}
                        onChange={(event) => {
                            setSelections((current) => ({ ...current, [room.id]: event.target.value }));
                            setFeedback((current) => ({ ...current, [room.id]: null }));
                        }}
                        disabled={savingRoom === room.id}
                    >
                        {roomStatuses.map((status) => <option value={status} key={status}>{status.replaceAll("_", " ")}</option>)}
                    </select>
                </td>
                <td>
                    <button className="btn btn-ghost btn-sm" type="button" disabled={!token || savingRoom === room.id || (selections[room.id] || room.status) === room.status} onClick={() => saveStatus(room)}>
                        {savingRoom === room.id ? "Saving…" : "Save status"}
                    </button>
                    {roomFeedback && <span className={`room-status-feedback ${roomFeedback.type}`} role={roomFeedback.type === "error" ? "alert" : "status"}>{roomFeedback.text}</span>}
                </td>
            </tr>
        );
    }
}
