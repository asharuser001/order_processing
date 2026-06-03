import React, { useState, useEffect, useCallback } from "react";
import { Page, Button, TextField, Select, Pagination } from "@shopify/polaris";
import { useNavigate } from "react-router-dom";
import { createAuthenticatedClient, api } from "../Services/api";
import LoadingState from "../Components/Common/LoadingState";
import ErrorState from "../Components/Common/ErrorState";
import EmptyState from "../Components/Common/EmptyState";

/* ── status pill ──────────────────────────────────────────────── */
function Pill({ label, color, bg }) {
    return (
        <span style={{
            display: "inline-flex", alignItems: "center", gap: "5px",
            padding: "3px 10px", borderRadius: "100px",
            background: bg, color, fontSize: "12px", fontWeight: "600", whiteSpace: "nowrap",
        }}>
            <span style={{ width: "6px", height: "6px", borderRadius: "50%", background: color, display: "inline-block", flexShrink: 0 }} />
            {label}
        </span>
    );
}

const FIN_PILL = {
    paid:           () => <Pill label="Paid"      color="#108043" bg="#e3f1df" />,
    pending:        () => <Pill label="Pending"   color="#916a00" bg="#fdf9ee" />,
    voided:         () => <Pill label="Voided"    color="#6d7175" bg="#f1f2f3" />,
    refunded:       () => <Pill label="Refunded"  color="#5c6ac4" bg="#f4f5fa" />,
    partially_paid: () => <Pill label="Partial"   color="#c05717" bg="#fdf6ef" />,
};
const STAGE_PILL = {
    created:   () => <Pill label="Created"   color="#00a0ac" bg="#f0fafa" />,
    paid:      () => <Pill label="Paid"      color="#108043" bg="#e3f1df" />,
    fulfilled: () => <Pill label="Fulfilled" color="#006e52" bg="#ecf5f2" />,
    cancelled: () => <Pill label="Cancelled" color="#de3618" bg="#fbe9e7" />,
    updated:   () => <Pill label="Updated"   color="#5c6ac4" bg="#f4f5fa" />,
};
const getPill = (map, key) => {
    const fn = map[key];
    return fn ? fn() : <Pill label={key ?? "—"} color="#6d7175" bg="#f1f2f3" />;
};

/* ── column header ────────────────────────────────────────────── */
const TH = ({ children, align = "left" }) => (
    <th style={{
        padding: "10px 16px", textAlign: align,
        fontSize: "11px", fontWeight: "700", color: "#6d7175",
        textTransform: "uppercase", letterSpacing: "0.06em",
        borderBottom: "2px solid #e1e3e5", background: "#fafbfb",
        whiteSpace: "nowrap",
    }}>{children}</th>
);

/* ── table cell ───────────────────────────────────────────────── */
const TD = ({ children, align = "left", mono = false }) => (
    <td style={{
        padding: "12px 16px", textAlign: align, verticalAlign: "middle",
        fontSize: "13px", color: "#202223",
        fontFamily: mono ? "monospace" : "inherit",
        borderBottom: "1px solid #f1f2f3",
    }}>{children}</td>
);

/* ── ══════════════════════ ORDERS PAGE ═══════════════════════── */
export default function OrdersPage() {
    const client   = createAuthenticatedClient();
    const navigate = useNavigate();

    const [orders,  setOrders]  = useState([]);
    const [meta,    setMeta]    = useState(null);
    const [loading, setLoading] = useState(true);
    const [error,   setError]   = useState(null);
    const [page,    setPage]    = useState(1);
    const [hovered, setHovered] = useState(null);

    const [search,             setSearch]             = useState("");
    const [financialStatus,    setFinancialStatus]    = useState("");
    const [fulfillmentStatus,  setFulfillmentStatus]  = useState("");
    const [stage,              setStage]              = useState("");

    const fetchOrders = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await api.getOrders(client, {
                search:             search || undefined,
                financial_status:   financialStatus || undefined,
                fulfillment_status: fulfillmentStatus || undefined,
                stage:              stage || undefined,
                page,
                per_page: 20,
            });
            setOrders(res.data.data.data ?? []);
            setMeta(res.data.data);
        } catch (err) {
            setError(err.response?.data?.message || "Failed to load orders.");
        } finally {
            setLoading(false);
        }
    }, [search, financialStatus, fulfillmentStatus, stage, page]);

    useEffect(() => { fetchOrders(); }, [fetchOrders]);

    return (
        <Page title="Orders" subtitle="Search, filter, and view order timelines.">
            <div style={{ background: "#fff", borderRadius: "12px", border: "1px solid #e1e3e5", boxShadow: "0 1px 4px rgba(0,0,0,0.05)", overflow: "hidden" }}>

                {/* ── filter toolbar ── */}
                <div style={{ padding: "14px 20px", borderBottom: "1px solid #f1f2f3", background: "#fafbfb", display: "flex", gap: "10px", flexWrap: "wrap", alignItems: "flex-end" }}>
                    <div style={{ flexGrow: 1, minWidth: 220 }}>
                        <TextField
                            label="Search" labelHidden
                            placeholder="Search order # or customer email…"
                            value={search} onChange={setSearch}
                            clearButton onClearButtonClick={() => setSearch("")}
                        />
                    </div>
                    <div style={{ minWidth: 150 }}>
                        <Select label="Financial" labelHidden
                            options={[
                                { label: "All payments",  value: "" },
                                { label: "Paid",          value: "paid" },
                                { label: "Pending",       value: "pending" },
                                { label: "Voided",        value: "voided" },
                                { label: "Refunded",      value: "refunded" },
                                { label: "Partial",       value: "partially_paid" },
                            ]}
                            value={financialStatus} onChange={setFinancialStatus}
                        />
                    </div>
                    <div style={{ minWidth: 140 }}>
                        <Select label="Stage" labelHidden
                            options={[
                                { label: "All stages", value: "" },
                                { label: "Created",    value: "created" },
                                { label: "Paid",       value: "paid" },
                                { label: "Fulfilled",  value: "fulfilled" },
                                { label: "Cancelled",  value: "cancelled" },
                            ]}
                            value={stage} onChange={setStage}
                        />
                    </div>
                    <Button onClick={fetchOrders} size="slim">Refresh</Button>
                </div>

                {/* ── content ── */}
                {loading ? (
                    <LoadingState label="Loading orders…" />
                ) : error ? (
                    <ErrorState message={error} onRetry={fetchOrders} />
                ) : orders.length === 0 ? (
                    <EmptyState heading="No orders found" description="Try adjusting your search or sync orders from the Dashboard." />
                ) : (
                    <>
                        <div style={{ overflowX: "auto" }}>
                            <table style={{ width: "100%", borderCollapse: "collapse", minWidth: "700px" }}>
                                <thead>
                                    <tr>
                                        <TH>Order</TH>
                                        <TH>Customer</TH>
                                        <TH>Email</TH>
                                        <TH>Payment</TH>
                                        <TH>Stage</TH>
                                        <TH align="right">Total</TH>
                                        <TH align="center">Action</TH>
                                    </tr>
                                </thead>
                                <tbody>
                                    {orders.map((order) => (
                                        <tr
                                            key={order.id}
                                            onMouseEnter={() => setHovered(order.id)}
                                            onMouseLeave={() => setHovered(null)}
                                            style={{ background: hovered === order.id ? "#f9fafb" : "#fff", cursor: "default", transition: "background 0.12s" }}
                                        >
                                            <TD>
                                                <span style={{ fontWeight: "600", color: "#202223" }}>{order.order_name}</span>
                                            </TD>
                                            <TD>{order.customer_name ?? "—"}</TD>
                                            <TD mono>{order.customer_email ?? "—"}</TD>
                                            <TD>{getPill(FIN_PILL, order.financial_status)}</TD>
                                            <TD>{getPill(STAGE_PILL, order.current_stage)}</TD>
                                            <TD align="right">
                                                <span style={{ fontWeight: "600" }}>
                                                    {order.currency} {parseFloat(order.total_price || 0).toFixed(2)}
                                                </span>
                                            </TD>
                                            <TD align="center">
                                                <button
                                                    onClick={() => navigate(`/orders/${order.id}`)}
                                                    style={{ background: "none", border: "1px solid #c4cdd5", borderRadius: "6px", padding: "5px 12px", fontSize: "12px", fontWeight: "600", color: "#202223", cursor: "pointer", whiteSpace: "nowrap" }}
                                                >
                                                    Timeline →
                                                </button>
                                            </TD>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* ── pagination ── */}
                        <div style={{ padding: "14px 20px", borderTop: "1px solid #f1f2f3", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                            <span style={{ fontSize: "13px", color: "#6d7175" }}>
                                {meta?.total ?? orders.length} orders · Page {page} of {meta?.last_page ?? 1}
                            </span>
                            <Pagination
                                hasPrevious={page > 1}
                                onPrevious={() => setPage((p) => p - 1)}
                                hasNext={meta?.last_page > page}
                                onNext={() => setPage((p) => p + 1)}
                            />
                        </div>
                    </>
                )}
            </div>
        </Page>
    );
}