<?php

// Cron job run once daily

require_once (__DIR__ . "/includes/config.inc.php");
require_once (__DIR__ . "/includes/dbh.inc.php");
require_once (__DIR__ . "/includes/users.class.php");
require_once (__DIR__ . "/includes/reviews.class.php");
require_once (__DIR__ . "/my-portal/includes/display_products.class.php");

$user_ids = user::getUserIds($pdo);

foreach ($user_ids as $user_id){

	// for each user, get existing reviews, if any then calculate new weight:

	$reviews = review::getRatingsById($pdo, $user_id);
	
	if($reviews){
		rank_by_keyword($pdo, $reviews, $user_id);
	}
}

function rank_by_keyword($pdo, $reviews, $user_id)
{
	$scores = [];
	$rating = 0;

	foreach ($reviews as $review){
		$product_id = $review['product_id'];
		$keyword = product::getKeywordsByProductId($pdo, $product_id);

		$created_date = $review['created_at'];
		$current_date = new DateTime(now);

		// $days = $current_date->diff($created_date)->days;
		$days = $current_date - $created_date;

		$weight = 1 / (1 + (0.01 * $days));
		$weighted_rating = $weight * $review['rating'];

		$scores[$keyword][] = $weighted_rating;
	}

	foreach ($scores as $score) {
		$keywords = $score;
		foreach ($score as $sc){
			$rating += $sc;
		}
		// search by keywords, order by rating
		user::updateRank($pdo, $rating, $keywords, $user_id);
	}
}


----------------------------------------------

class users {
public static function updateRank($pdo, $rating, $keywords, $user_id){
	$query = "UPDATE users SET rating = :rating, keywords = :keywords WHERE user_id = :user_id;";

	$stmt = $pdo->prepare($query);

	$stmt = bindParams(":user_id", $user_id);
	$stmt = bindParams(":keywords", $keywords);
	$stmt = bindParams(":rating", $rating);

	$stmt->execute();
}

public static function getUserIds($pdo){
	$query = "GET user_id FROM users";

	$stmt = $pdo->prepare($query);
	$results = $stmt->execute();

	return $results;
}

}

class product {
public static function getKeywordsByProductId($pdo, $product_id){
	$query = "GET keywords FROM products WHERE product_id = :product_id";

	$stmt = $pdo->prepare($query);

	$stmt = bindParams(":product_id", $product_id);

	$results = $stmt->execute();

	return $results;
}
}
