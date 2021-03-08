SELECT
	date_format( max( created_at ), '%m/%Y' ) as "date",
	COUNT( status ) as "status",
	COUNT( res_order_status ) as "res_order_status",
	COUNT( receipt_status ) as "receipt_status",
	COUNT( shipping_status ) as "shipping_status",
	SUM( final_total ) as "total"
FROM
	transactions
	WHERE business_id="$business_id"
	AND location_id="$location_id"
	AND type="$type"
	AND status="approve"
	AND DATE(created_at) BETWEEN DATE("$start_date") AND DATE("$end_date")
GROUP BY
	MONTH ( created_at ),
	YEAR ( created_at )
ORDER BY created_at asc
