SELECT
	'receive' AS 'accountant_type',
	pb.type,
	SUM( IF ( pb.method = '$payment_method', pb.amount, 0 ) ) AS 'cash'
FROM
	pay_bills AS pb
	JOIN accountants AS acc ON acc.id = pb.accountant_id
WHERE
    acc.location_id = '$location_id'
	AND ( acc.type = 'receive'
	OR acc.type = '$accountant_type_receive' )
	AND pb.type <> '$payment_type_receive'
	AND pb.method = '$payment_method'
	AND  DATE( date_format( pb.created_at, '%Y-%m-%d' ) )
	BETWEEN DATE( '$start_date' )
	AND DATE( '$end_date' )
GROUP BY
	acc.type,
	pb.type,
	pb.method UNION ALL
SELECT
	'pay' AS 'accountant_type',
	pb.type,
	SUM( IF ( pb.method = '$payment_method', pb.amount, 0 ) ) AS 'cash'
FROM
	pay_bills AS pb
	JOIN accountants AS acc ON acc.id = pb.accountant_id
WHERE
    acc.location_id = '$location_id'
	AND ( acc.type = 'pay'
	OR acc.type = '$accountant_type_pay' )
	AND pb.type <> '$payment_type_pay'
	AND pb.method = '$payment_method'
	AND DATE( date_format( pb.created_at, '%Y-%m-%d' ) )
	BETWEEN DATE( '$start_date' )
	AND DATE( '$end_date' )
GROUP BY
	acc.type,
	pb.type,
	pb.method
