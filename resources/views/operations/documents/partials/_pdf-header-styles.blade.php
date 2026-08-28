.header {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 2.45in;
    gap: 0.24in;
    align-items: start;
    padding: 0 0 0.09in;
    border-bottom: 1px solid #cbd5e1;
}

.document-heading {
    min-width: 0;
}

.logo-mark {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    width: 1.45in;
    height: 0.65in;
    background: #ffffff;
    color: #334155;
    font-size: 14px;
    font-weight: 800;
    line-height: 1;
}

.logo-mark img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.shop-contact {
    text-align: right;
}

.shop-contact h1 {
    margin: 0;
    font-size: 14px;
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -0.01em;
    white-space: nowrap;
}

.eyebrow {
    margin: 0 0 0.05in;
    color: #64748b;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.identity-band {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.1in;
    padding: 0.075in 0;
    border-bottom: 1px solid #e2e8f0;
}

.identity-col h3 {
    margin: 0.03in 0 0.04in;
    font-size: 12px;
    line-height: 1.25;
}

.identity-address-locality {
    margin: 0;
    padding-left: 2.85rem;
}

.muted {
    color: #64748b;
}

.small {
    font-size: 9px;
}

.vehicle-descriptor {
    margin: 0 0 0.03in;
    font-size: 9px;
}

.sheet-context {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 0.12in;
    align-items: end;
    padding: 0.08in 0 0.06in;
    border-bottom: 1px solid #e2e8f0;
}

.sheet-context-title {
    color: #0f172a;
    font-size: 16px;
    font-weight: 900;
    letter-spacing: -0.02em;
    line-height: 1.05;
}

.sheet-context-meta {
    color: #64748b;
    font-size: 9px;
    font-weight: 600;
    text-align: right;
    white-space: nowrap;
}
