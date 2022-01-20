SELECT
	'receive' AS 'accountant_type',
	pb.type,
	SUM(  pb.amount) AS 'cash'
FROM
	pay_bills AS pb
	JOIN accountants AS acc ON acc.id = pb.accountant_id
WHERE
    acc.type = 'receive'
    AND acc.location_id = '$location_id'
	AND pb.type <> '$payment_type_receive'
	AND  DATE( date_format( pb.created_at, '%Y-%m-%d' ) )
	BETWEEN DATE( '$start_date' )
	AND DATE( '$end_date' )
GROUP BY
	acc.type,
	pb.type
	 UNION ALL
SELECT
	'pay' AS 'accountant_type',
	pb.type,
	SUM( pb.amount ) AS 'cash'
FROM
	pay_bills AS pb
	JOIN accountants AS acc ON acc.id = pb.accountant_id
WHERE
   acc.type = 'pay'
    AND acc.location_id = '$location_id'
	AND pb.type <> '$payment_type_pay'
	AND DATE( date_format( pb.created_at, '%Y-%m-%d' ) )
	BETWEEN DATE( '$start_date' )
	AND DATE( '$end_date' )
GROUP BY
	acc.type,
	pb.type
