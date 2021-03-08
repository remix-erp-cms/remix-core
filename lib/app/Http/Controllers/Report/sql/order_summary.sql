SELECT
	COUNT( id ) as "total_order",
	SUM( final_total ) as "final_total",
	SUM( final_profit ) as "final_profit"
FROM
	transactions
	WHERE business_id="$business_id"
	AND location_id="$location_id"
	AND type="$type"
	AND status="approve"
	AND DATE(created_at) BETWEEN DATE("$start_date") AND DATE("$end_date")
