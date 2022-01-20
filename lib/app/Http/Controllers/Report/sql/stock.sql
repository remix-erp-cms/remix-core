SELECT
	SUM( unit_price * quantity ) AS total_sell,
	SUM( purchase_price * quantity ) AS total_purchase,
	COUNT(id) as total_product
FROM
	`stock_products`
WHERE
	quantity > 0;
