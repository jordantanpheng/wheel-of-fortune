<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require '../vendor/autoload.php';

class MyDB extends SQLite3 {
    function __construct() {
        $this->open('../wof.db');
    }
}

$db = new MyDB();
if (!$db) {
    echo $db->lastErrorMsg();
    exit();
}

$app = new \Slim\App;

// Create a game
$app->post(
    '/api/create',
    function (Request $request, Response $response, array $args) use ($db) {
        $requestData = $request->getParsedBody();
        if (!isset($requestData['id'])) {
            return $response->withStatus(400)->withJson(['error' => 'id is required']);
        }
		// Insert the ID of this game to database
        $sql1 = "INSERT INTO games (gameID) VALUES (:id)";
        $stmt1 = $db->prepare($sql1);
        $stmt1->bindValue('id', $requestData['id']);
        $stmt1->execute();	
		// Update the status of this game to database
        $sql2 = "UPDATE games SET status = :status WHERE gameID = :id";
        $stmt2 = $db->prepare($sql2);
        $stmt2->bindValue('status', 'waitingPlayers');
		$stmt2->bindValue('id', $requestData['id']);
        $stmt2->execute();			
		// Select a random puzzle from database
		$sql3 = "SELECT puzzle FROM puzzles ORDER BY RANDOM() LIMIT 1";
        $stmt3 = $db->prepare($sql3);
        $ret3 = $stmt3->execute();
		$puzzle = $ret3->fetchArray(SQLITE3_ASSOC);
		// Update the puzzle of this game to database
		$sql4 = "UPDATE games SET puzzle = :puzzle WHERE gameID = :id";
        $stmt4 = $db->prepare($sql4);
		$stmt4->bindValue('id', $requestData['id']);
        $stmt4->bindValue('puzzle', $puzzle['puzzle']);
        $stmt4->execute();
        return $response->withStatus(201)->withJson($requestData);
    }
);

// Get list of players
$app->get(
	'/api/lobby/{id}/players',
    function (Request $request, Response $response, array $args) use ($db) {
		// Check if this game exist
		$sql1 = "SELECT gameID FROM games WHERE gameID = :id";
		$stmt1 = $db->prepare($sql1);
		$stmt1->bindValue('id', $args['id']);
		$ret1 = $stmt1->execute();
		$gameData = $ret1->fetchArray(SQLITE3_ASSOC);
		if ($gameData) {
			// Get list of players
			$sql2 = "SELECT player FROM players WHERE gameID = :id";
			$stmt2 = $db->prepare($sql2);
			$stmt2->bindValue('id', $args['id']);
			$ret2 = $stmt2->execute();
			$playersList = array();
			while ($player = $ret2->fetchArray(SQLITE3_ASSOC))
			{
				array_push($playersList, $player);
			}
			return $response->withStatus(200)->withJson($playersList);			
		} else {
			return $response->withStatus(404)->withJson(['error' => 'This game does not exist']);
		}
    }
);

// Get game status in lobby
$app->get(
	'/api/lobby/{id}/status',
    function (Request $request, Response $response, array $args) use ($db) {
		// Check if this game exist
		$sql1 = "SELECT gameID FROM games WHERE gameID = :id";
		$stmt1 = $db->prepare($sql1);
		$stmt1->bindValue('id', $args['id']);
		$ret1 = $stmt1->execute();
		$gameData = $ret1->fetchArray(SQLITE3_ASSOC);
		if ($gameData) {
			// Get game status
			$sql2 = "SELECT status FROM games WHERE gameID = :id";
			$stmt2 = $db->prepare($sql2);
			$stmt2->bindValue('id', $args['id']);
			$ret2 = $stmt2->execute();
			$status = $ret2->fetchArray(SQLITE3_ASSOC);
			return $response->withStatus(200)->withJson($status);			
		} else {
			return $response->withStatus(404)->withJson(['error' => 'This game does not exist']);
		}
    }
);

// Get game status in game
$app->get(
	'/api/play/{id}/status',
    function (Request $request, Response $response, array $args) use ($db) {
		// Check if this game exist
		$sql1 = "SELECT gameID FROM games WHERE gameID = :id";
		$stmt1 = $db->prepare($sql1);
		$stmt1->bindValue('id', $args['id']);
		$ret1 = $stmt1->execute();
		$gameData = $ret1->fetchArray(SQLITE3_ASSOC);
		if ($gameData) {
			// Get game status
			$sql2 = "SELECT status FROM games WHERE gameID = :id";
			$stmt2 = $db->prepare($sql2);
			$stmt2->bindValue('id', $args['id']);
			$ret2 = $stmt2->execute();
			$status = $ret2->fetchArray(SQLITE3_ASSOC);
			return $response->withStatus(200)->withJson($status);			
		} else {
			return $response->withStatus(404)->withJson(['error' => 'This game does not exist']);
		}
    }
);

// Update game status to 'gameStarted'
$app->post(
    '/api/lobby/{id}/status/start',
    function (Request $request, Response $response, array $args) use ($db) {
		$sql1 = "UPDATE games SET status = :status WHERE gameID = :id";
		$stmt1 = $db->prepare($sql1);
		$stmt1->bindValue('status', 'gameStarted');
		$stmt1->bindValue('id', $args['id']);
		$ret1 = $stmt1->execute();
	}
);

// Refresh game status
$app->get(
	'/api/play/{id}',
    function (Request $request, Response $response, array $args) use ($db) {
		// Check if this game exist
		$sql1 = "SELECT gameID FROM games WHERE gameID = :id";
		$stmt1 = $db->prepare($sql1);
		$stmt1->bindValue('id', $args['id']);
		$ret1 = $stmt1->execute();
		$gameData = $ret1->fetchArray(SQLITE3_ASSOC);
		// If this game exist
		if ($gameData) {	
			// Get the puzzle of this game 
			$sql2 = "SELECT puzzle FROM games WHERE gameID = :id";
			$stmt2 = $db->prepare($sql2);
			$stmt2->bindValue('id', $args['id']);
			$ret2 = $stmt2->execute();
			//$gameData = $gameData + $ret2->fetchArray(SQLITE3_ASSOC);
			$puzzle = $ret2->fetchArray(SQLITE3_ASSOC);
			$puzzle_split = str_split($puzzle['puzzle'], 1);
			// Get the letters that were used
			$sql3 = "SELECT letters FROM games WHERE gameID = :id";
			$stmt3 = $db->prepare($sql3);
			$stmt3->bindValue('id', $args['id']);
			$ret3 = $stmt3->execute();
			$letters = $ret3->fetchArray(SQLITE3_ASSOC);
			$letters_split = str_split($letters['letters'], 1);
			// Initialize puzzle to display by setting all the letters to "_"
			$puzzle_to_display = [];
			for ($i = 0; $i < count($puzzle_split); $i++) {
				$puzzle_to_display[$i] = "_";
			}
			// Replace "_" by the letters used
			$count = 0;
			foreach($puzzle_split as $letter_of_puzzle) {
				foreach($letters_split as $letter){
					if ($letter_of_puzzle == $letter) {
						$puzzle_to_display[$count] = $letter;
					}
				}
				$count++;
			}
			// Convert this puzzle (array) in puzzle (string)
			$puzzle_to_display = implode("", $puzzle_to_display);
			return $response->withStatus(200)->withJson($puzzle_to_display);
		} else {
			return $response->withStatus(404)->withJson(['error' => 'This game does not exist']);
		}
    }
);

// Player set his username
$app->post(
    '/api/lobby/{id}/{username}',
    function (Request $request, Response $response, array $args) use ($db) {
		// Get the letters that were used
		$sql1 = "INSERT INTO players (player, gameID) VALUES (:username, :id)";
		$stmt1 = $db->prepare($sql1);
		$stmt1->bindValue('username', $args['username']);
		$stmt1->bindValue('id', $args['id']);
		$ret1 = $stmt1->execute();
	}
);

// Submit a letter
$app->post(
    '/api/play/{id}',
    function (Request $request, Response $response, array $args) use ($db) {
        $requestData = $request->getParsedBody();
        if (!isset($requestData['letter'])) {
            return $response->withStatus(400)->withJson(['error' => 'letter is required']);
        }
		// Get the letters that were used
		$sql1 = "SELECT letters FROM games WHERE gameID = :id";
		$stmt1 = $db->prepare($sql1);
		$stmt1->bindValue('id', $args['id']);
		$ret1 = $stmt1->execute();
		$letters = $ret1->fetchArray(SQLITE3_ASSOC);
		$letters_split = str_split($letters['letters'], 1);
		// We add the letter if it is not used
		if (!in_array($requestData['letter'], $letters_split)) {
			array_push($letters_split, $requestData['letter']);
		} else {
			return $response->withStatus(200)->withJson('Letter has already been used');
		}
		// Convert these letters (array) in letters (string)
		$letters = implode("", $letters_split);
		// Update the letters of this game to database
		$sql2 = "UPDATE games SET letters = :letters WHERE gameID = :id";
        $stmt2 = $db->prepare($sql2);
		$stmt2->bindValue('id', $args['id']);
        $stmt2->bindValue('letters', $letters);
        $stmt2->execute();
        return $response->withStatus(200)->withJson('Letter has successfully been sent');
    }
);
$app->run();
