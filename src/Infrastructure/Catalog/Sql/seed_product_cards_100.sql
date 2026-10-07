DO $$
DECLARE
    active_category_table text;
    import_session uuid := '00000000-0000-4000-8000-000000000100';
    supplier_uuid uuid := '11111111-1111-4111-8111-111111111111';
    stock_uuid uuid := '22222222-2222-4222-8222-222222222222';
    active_categories_count integer;
BEGIN
    SELECT table_name
    INTO active_category_table
    FROM catalog.catalog_table_versions
    WHERE original_name = 'categories'
      AND is_active = true
    ORDER BY switched_at DESC NULLS LAST, id DESC
    LIMIT 1;

    IF active_category_table IS NULL THEN
        RAISE EXCEPTION 'Active catalog category table not found';
    END IF;

    EXECUTE format(
        'SELECT count(*)
         FROM catalog.%I
         WHERE is_active = true',
        active_category_table
    )
    INTO active_categories_count;

    IF active_categories_count = 0 THEN
        RAISE EXCEPTION 'Active catalog category table % has no active categories', active_category_table;
    END IF;

    EXECUTE format(
        $sql$
        WITH active_categories AS (
            SELECT
                external_id,
                row_number() OVER (ORDER BY level ASC, sort_order ASC, name ASC, external_id ASC) AS rn,
                count(*) OVER () AS total_count
            FROM catalog.%I
            WHERE is_active = true
              AND external_id IS NOT NULL
        ),
        generated_products AS (
            SELECT
                gs.n,
                ac.external_id AS category_id,
                'TEST-PRODUCT-' || lpad(gs.n::text, 3, '0') AS external_code,
                $1::uuid AS supplier_id,
                $2::uuid AS stock_id,
                'Тестовый товар ' || lpad(gs.n::text, 3, '0') AS name,
                'testovyy_tovar_' || lpad(gs.n::text, 3, '0') AS slug,
                (10 + (gs.n %% 41))::numeric(18, 2) AS quantity,
                CASE
                    WHEN gs.n %% 3 = 0 THEN 'кг'
                    WHEN gs.n %% 3 = 1 THEN 'шт'
                    ELSE 'м'
                END AS unit,
                (1000 + gs.n * 137)::numeric(18, 2) AS price,
                CASE
                    WHEN gs.n %% 5 = 0 THEN 'KZT'
                    ELSE 'RUR'
                END AS currency,
                'https://static.onmi.test/catalog/products/testovyy_tovar_' || lpad(gs.n::text, 3, '0') || '.jpg' AS image_url,
                $3::uuid AS import_session_id,
                'active' AS status
            FROM generate_series(1, 100) AS gs(n)
            INNER JOIN active_categories ac
                ON ac.rn = ((gs.n - 1) %% ac.total_count) + 1
        )
        INSERT INTO catalog.product_cards (
            category_id,
            external_code,
            supplier_id,
            stock_id,
            name,
            slug,
            quantity,
            unit,
            price,
            currency,
            image_url,
            import_session_id,
            status,
            created_at,
            updated_at
        )
        SELECT
            category_id,
            external_code,
            supplier_id,
            stock_id,
            name,
            slug,
            quantity,
            unit,
            price,
            currency,
            image_url,
            import_session_id,
            status,
            NOW(),
            NOW()
        FROM generated_products
        ON CONFLICT (supplier_id, stock_id, external_code)
        DO UPDATE SET
            category_id = EXCLUDED.category_id,
            name = EXCLUDED.name,
            slug = EXCLUDED.slug,
            quantity = EXCLUDED.quantity,
            unit = EXCLUDED.unit,
            price = EXCLUDED.price,
            currency = EXCLUDED.currency,
            image_url = EXCLUDED.image_url,
            import_session_id = EXCLUDED.import_session_id,
            status = EXCLUDED.status,
            updated_at = NOW()
        $sql$,
        active_category_table
    )
    USING
        supplier_uuid,
        stock_uuid,
        import_session;
END $$;
