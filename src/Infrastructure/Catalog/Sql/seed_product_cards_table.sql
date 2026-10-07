-- Table: catalog.product_cards

-- DROP TABLE IF EXISTS catalog.product_cards;

CREATE TABLE IF NOT EXISTS catalog.product_cards
(
    id BIGSERIAL PRIMARY KEY,

    category_id VARCHAR(100),
    external_code VARCHAR(100) NOT NULL,

    supplier_id UUID NOT NULL,
    stock_id UUID NOT NULL,

    name VARCHAR(500) NOT NULL,
    slug VARCHAR(500) NOT NULL,

    quantity NUMERIC(18, 2),
    unit VARCHAR(50),

    price NUMERIC(18, 2),
    currency VARCHAR(3),

    image_url TEXT,
    import_session_id UUID,

    is_active BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),

    CONSTRAINT uq_product_cards_slug
        UNIQUE (slug),

    CONSTRAINT uq_product_cards_supplier_stock_external_code
        UNIQUE (supplier_id, stock_id, external_code)
);

ALTER TABLE IF EXISTS catalog.product_cards
    OWNER TO symfony;

-- Index: idx_product_cards_category_id

-- DROP INDEX IF EXISTS catalog.idx_product_cards_category_id;

CREATE INDEX IF NOT EXISTS idx_product_cards_category_id
    ON catalog.product_cards USING btree (category_id ASC NULLS LAST)
    WITH (fillfactor = 100, deduplicate_items = TRUE)
    TABLESPACE pg_default;
