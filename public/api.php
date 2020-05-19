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
		// Initialize order of players
		$turnsOrder = array();
		for ($i = 0; $i < count($playersList); $i++) {
			array_push($turnsOrder, $i);
		}
		shuffle($turnsOrder);
		$turnsOrder = implode("", $turnsOrder);
		// Update first player to play
		$sql3 = "UPDATE games SET nextTurn = :firstTurn WHERE gameID = :id";
		$stmt3 = $db->prepare($sql3);
		$stmt3->bindValue('firstTurn', $turnsOrder[0]);
		$stmt3->bindValue('id', $args['id']);
		$ret3 = $stmt3->execute();	
		// Update order of players and number of puzzles to this game to database
		$puzzlesLeft = count($playersList)+1;
		$sql4 = "UPDATE games SET turnsOrder = :order, puzzlesLeft = :games WHERE gameID = :id";
		$stmt4 = $db->prepare($sql4);
		$stmt4->bindValue('order', $turnsOrder);
		$stmt4->bindValue('games', $puzzlesLeft);
		$stmt4->bindValue('id', $args['id']);
		$ret4 = $stmt4->execute();
	
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
		$gameID = $ret1->fetchArray(SQLITE3_ASSOC);
		// If this game exist
		if ($gameID) {	
			// Get the puzzle of this game 
			$sql2 = "SELECT puzzle FROM games WHERE gameID = :id";
			$stmt2 = $db->prepare($sql2);
			$stmt2->bindValue('id', $args['id']);
			$ret2 = $stmt2->execute();
			$puzzle = $ret2->fetchArray(SQLITE3_ASSOC);
			$puzzle_split = str_split($puzzle['puzzle'], 1);
			// Get the letters that were used
			$sql3 = "SELECT letters FROM games WHERE gameID = :id";
			$stmt3 = $db->prepare($sql3);
			$stmt3->bindValue('id', $args['id']);
			$ret3 = $stmt3->execute();
			$letters = $ret3->fetchArray(SQLITE3_ASSOC);
			$gameData = array();
			$gameData['letters'] = $letters;
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
			$gameData['puzzle'] = $puzzle_to_display;
			// Get player points
			$sql4 = "SELECT player, points FROM players WHERE gameID = :id";
			$stmt4 = $db->prepare($sql4);
			$stmt4->bindValue('id', $args['id']);
			$ret4 = $stmt4->execute();
			$playersPoints = array();
			while ($points = $ret4->fetchArray(SQLITE3_ASSOC))
			{
				array_push($playersPoints, $points);
			}
			$gameData['points'] = $playersPoints;
			// Get list of players
			$sql5 = "SELECT player FROM players WHERE gameID = :id";
			$stmt5 = $db->prepare($sql5);
			$stmt5->bindValue('id', $args['id']);
			$ret5 = $stmt5->execute();
			$playersList = array();
			while ($player = $ret5->fetchArray(SQLITE3_ASSOC))
			{
				array_push($playersList, $player);
			}
			// Get username of current player
			$sql6 = "SELECT nextTurn FROM games WHERE gameID = :id";
			$stmt6 = $db->prepare($sql6);
			$stmt6->bindValue('id', $args['id']);
			$ret6 = $stmt6->execute();
			$nextTurn = $ret6->fetchArray(SQLITE3_ASSOC);
			$currentPlayer = $playersList[$nextTurn['nextTurn']];
			$gameData['currentPlayer'] = $currentPlayer;
			return $response->withStatus(200)->withJson($gameData);
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
		$sql1 = "INSERT INTO players (player, gameID, points) VALUES (:username, :id, :points)";
		$stmt1 = $db->prepare($sql1);
		$stmt1->bindValue('username', $args['username']);
		$stmt1->bindValue('id', $args['id']);
		$stmt1->bindValue('points', 0);
		$ret1 = $stmt1->execute();
	}
);

// Submit player value, attribute points and change which player has to play
$app->post(
    '/api/play/{id}',
    function (Request $request, Response $response, array $args) use ($db) {
        $requestData = $request->getParsedBody();
		// Get players list
		$sql1 = "SELECT player FROM players WHERE gameID = :id";
		$stmt1 = $db->prepare($sql1);
		$stmt1->bindValue('id', $args['id']);
		$ret1 = $stmt1->execute();
		$playersList = array();
		while ($player = $ret1->fetchArray(SQLITE3_ASSOC))
		{
			array_push($playersList, $player);
		}
		// Get player turn
		$sql2 = "SELECT nextTurn FROM games WHERE gameID = :id";
		$stmt2 = $db->prepare($sql2);
		$stmt2->bindValue('id', $args['id']);
		$ret2 = $stmt2->execute();
		$nextTurn = $ret2->fetchArray(SQLITE3_ASSOC);
		$currentPlayer = $playersList[$nextTurn['nextTurn']];
		// Check if this is player's turn
		if ($currentPlayer['player'] == $requestData['player']) {
			// Get turns order
			$sql3 = "SELECT turnsOrder FROM games WHERE gameID = :id";
			$stmt3 = $db->prepare($sql3);
			$stmt3->bindValue('id', $args['id']);
			$ret3 = $stmt3->execute();
			$turnsOrder = $ret3->fetchArray(SQLITE3_ASSOC);
			$turnsOrder_split = str_split($turnsOrder['turnsOrder']);
			$key = array_search(strval($nextTurn['nextTurn']), $turnsOrder_split);
			// We verify if he is the last player of this round
			if ( $key + 1 == count($turnsOrder_split)) {
				$nextTurn = $turnsOrder_split[0];
			}
			else {
				$nextTurn = $turnsOrder_split[$key+1];
			}
			// Update the player turn
			$sql4 = "UPDATE games SET nextTurn = :nextPlayer WHERE gameID = :id";
			$stmt4 = $db->prepare($sql4);
			$stmt4->bindValue('nextPlayer', $nextTurn);
			$stmt4->bindValue('id', $args['id']);
			$ret4 = $stmt4->execute();		
			// If player value is null
			if (!isset($requestData['playerValue'])) {
				return $response->withStatus(200)->withJson('A value is required !');
			}
			// If player value contains a special character
			if (preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', $requestData['playerValue'])) {
				return $response->withStatus(200)->withJson('No special character allowed !');
			}
			// Put player value to lowercase
			$requestData['playerValue'] = strtolower($requestData['playerValue']);
			// Check player value length
			$length = strlen($requestData['playerValue']);
			// Get puzzle value
			$sql5 = "SELECT puzzle FROM games WHERE gameID = :id";
			$stmt5 = $db->prepare($sql5);
			$stmt5->bindValue('id', $args['id']);
			$ret5 = $stmt5->execute();
			$puzzle = $ret5->fetchArray(SQLITE3_ASSOC);
			// If length is more than 1 character we check if the player guessed the puzzle correctly
			if ($length > 1) {
				// If player guess correctly the puzzle
				if ($puzzle['puzzle'] == $requestData['playerValue']) {
					// Update the points won by the player
					$sql6 = "UPDATE players SET points = points + :length WHERE player = :player AND gameID = :id";
					$stmt6 = $db->prepare($sql6);
					$stmt6->bindValue('length', $length);
					$stmt6->bindValue('player', $requestData['player']);
					$stmt6->bindValue('id', $args['id']);
					$stmt6->execute();
					return $response->withStatus(200)->withJson('You guessed the puzzle correctly ! Congratulations !');
					// Complete the puzzle
				} else {
					return $response->withStatus(200)->withJson("Sorry you didn't guess the puzzle correctly");
				}	
			// else we check if the letter sent is correct
			} else {
				// Get the letters that were used
				$sql7 = "SELECT letters FROM games WHERE gameID = :id";
				$stmt7 = $db->prepare($sql7);
				$stmt7->bindValue('id', $args['id']);
				$ret7 = $stmt7->execute();
				$letters = $ret7->fetchArray(SQLITE3_ASSOC);
				$letters_split = str_split($letters['letters'], 1);
				// We add the letter if it is not used
				if (!in_array($requestData['playerValue'], $letters_split)) {
					array_push($letters_split, $requestData['playerValue']);
				} else {
					return $response->withStatus(200)->withJson('Letter has already been used !');
					//return $response->withStatus(200)->withJson($key);
				}
				// Convert these letters (array) in letters (string)
				$letters = implode("", $letters_split);
				// Update the letters of this game to database
				$sql8 = "UPDATE games SET letters = :letters WHERE gameID = :id";
				$stmt8 = $db->prepare($sql8);
				$stmt8->bindValue('id', $args['id']);
				$stmt8->bindValue('letters', $letters);
				$stmt8->execute();
				// If the letter is in the puzzle the player gain x points
				if (strpos($puzzle['puzzle'], $requestData['playerValue']) !== false) {
					$points = substr_count($puzzle['puzzle'], $requestData['playerValue']);
					$sql9 = "UPDATE players SET points = points + :point WHERE player = :player AND gameID = :id";
					$stmt9 = $db->prepare($sql9);
					$stmt9->bindValue('point', $points);
					$stmt9->bindValue('player', $requestData['player']);
					$stmt9->bindValue('id', $args['id']);
					$stmt9->execute();
					return $response->withStatus(200)->withJson('You guessed the letter correctly');
				} else {
					return $response->withStatus(200)->withJson('Sorry this letter is not part of the puzzle');
				}	
			}
		} else {
			return $response->withStatus(200)->withJson('This is not your turn !');
		}
    }
);
$app->run();
