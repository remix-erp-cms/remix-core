SELECT
	COUNT( id ) as "total_order",
	SUM( final_total ) as "final_total",
	SUM( final_profit ) as "final_profit"
FROM
	transactions
	WHERE  location_id="$location_id"
	AND receipt_status = "approve"
	AND type="$type"
	AND $create_by
	AND DATE(created_at) BETWEEN DATE("$start_date") AND DATE("$end_date")
