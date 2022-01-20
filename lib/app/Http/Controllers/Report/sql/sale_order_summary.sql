SELECT
	$status as status,
	COUNT( id ) as "total_order",
	SUM( final_total ) as "final_total"
FROM
	transactions
	WHERE  location_id="$location_id"
	AND type="$type"
	AND $condition
	AND $create_by
	AND DATE(created_at) BETWEEN DATE("$start_date") AND DATE("$end_date")
GROUP BY $status
