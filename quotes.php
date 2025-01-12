<?php

/**
*
* @author Brendan Dileo
*
* The purpose of this php script is to implement an infinitely scrolling and dynamically loading webpage of quotes. 
* The user can browse the webpage of quotes as is, or they can log into the webpage which allows them to favorite
* quotes by toggling a button. The script makes use of sessions to determine whether or not the user is logged
*  in, and a php data object to establish a connection to a database, that stores the quotes, users, and the users
* favorite quotes. Additionally, a function is included in the script to construct the html cards based on both
* the session, and the contents of the database.
*/

require 'config.php'; // includes the db config
session_start(); // starts the session

// Infinite Scroll
if ($_SERVER['REQUEST_METHOD'] == 'GET') { // checks if request method is GET
    if (isset($_GET['page'])) { // checks if the incoming GET 'page' variable is set
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT); // validate and sanitize the incoming value if the variable is set

        if ($page < 0 || $page === null || $page === false) { // confirms and validates the value, ensures it is positive
            echo json_encode([ "error" => "The provided parameter was invalid!"]); // sends an json error response to the client indicating an invalid value
            exit;
        }

        $max = 20; // max number of quotes allowed on page at a time
        $offset = ($page - 1) * $max; // determines the number of quotes to skip

        // initializes an sql query string to retrieve the id, author, and text of all quotes 
        $query = " 
            SELECT q.quote_id, a.author_name, q.quote_text
            FROM authors a
            JOIN quotes q
                ON a.author_id = q.author_id 
            LIMIT $max OFFSET $offset
        "; // limits query results based on the max and offset values

        try { 
            $stmt = $dbh->prepare($query); // prepares the sql query for execution
            $stmt->execute(); // executes the sql query
            $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC); // retrieves and stores all rows resulting from the query
            
            $cards = array(); // initializes an empty array to hold the quote cards
            foreach ($quotes as $quote) { // iterates through each quote in the array of quotes returned from the sql query
                $cards[] = createCard($quote['author_name'], $quote['quote_text'], $quote['quote_id']); // creates the card dynamically based on query
            }
            echo json_encode($cards); // sends the associative array containing the quote cards back to the client as a json response
        } catch (Exception $ex) {
            echo json_encode(["error" => "The query was not executed! Reason: " . $ex->getMessage()]); // sends a json response back to the client indicating an error
        }        
    } else { // the incoming GET variable isn't set
        echo json_encode(["error" => "The page number was not found!"]);
    }
}

// Login
if ($_SERVER['REQUEST_METHOD'] == 'POST') { // checks if the request method is POST
    if (isset($_POST['action']) && $_POST['action'] == 'login') { // checks if the incoming POST 'action' variable is set and if the request is for login
        $user = filter_input(INPUT_POST, 'username', FILTER_DEFAULT); // validates and sanitizes the incoming username value
        $pass = filter_input(INPUT_POST, 'password', FILTER_DEFAULT); // validates and sanitizes the incoming password value

        // initializes an sql query to retrieve the user id, username, and password for a user with the matching username
        $query = "
            SELECT user_id, username, password
            FROM users
            WHERE username = :username
        ";

        try {
            $stmt = $dbh->prepare($query); // prepares the sql query for execution
            $stmt->bindParam(':username', $user, PDO::PARAM_STR); // binds the username value to the placeholder in the sql query
            $stmt->execute(); // executes the sql query
            $user = $stmt->fetch(PDO::FETCH_ASSOC); // retrieves and stores a single row of data from the result of the executed query

            if ($user && password_verify($pass, $user['password'])) { // checks if the user exists and the password hash matches the one stored in the db
                $_SESSION['validated'] = $user['user_id']; // stores the value of the users id into the session
                echo "Login Successful!"; // sends a text response back to the client indicating a successful login

                // initializes an sql query string to retrieve the quote id, quote text, author of the quote for all of the quotes that the current user has favorited
                $q = "
                    SELECT q.quote_id, q.quote_text, a.author_name
                    FROM favorites f
                    JOIN quotes q
                        ON f.quote_id = q.quote_id
                    JOIN authors a
                        ON q.author_id = a.author_id
                    WHERE f.user_id = :user_id;
                ";

               $stmt = $dbh->prepare($q); // prepares the sql query string for execution
               $stmt->bindParam(':user_id', $_SESSION['validated'], PDO::PARAM_INT); // binds the user id stored in the session variable to the placeholder in the query
               $stmt->execute(); // executes the query
               $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC); // retrieves and stores all rows of data from the result of the query
            } else { // username doesn't exist or the password hashes do not match
                echo "The username or password is invalid!";
            }
        } catch (Exception $ex) {
            echo json_encode(["error" => "Login failed: " . $ex->getMessage()]); // sends json response back to the client indicating an error during login
            exit; 
        }
    }

    // Favorite a Quote
    if (isset($_POST['action']) && $_POST['action'] == 'favourite') { // checks if the incoming POST 'action' variable is set and if the request is to favorite a quote
        if (isset($_SESSION['validated'])) { // checks if the session variable 'validated' is set indicating the user is logged in
            $quoteID = filter_input(INPUT_POST, 'quote', FILTER_VALIDATE_INT); // validates the incoming quote id variable
            $favourited = filter_input(INPUT_POST, 'check', FILTER_VALIDATE_BOOLEAN); // validates the incoming check variable

            if ($quoteID == null || $quoteID == false) { // checks if the quote id is invalid or missing
                echo json_encode(["error" => "The quote was invalid!"]);
                exit;
            }

            try {
                if ($favourited) { // checks if a quote is favourited
                    $q = "INSERT INTO favorites (user_id, quote_id) VALUES (:user_id, :quote_id)"; // initializes an sql query string to add the favourited quote to the users favourites
                    $stmt = $dbh->prepare($q); // prepares the sql query string for execution
                    $stmt->bindParam(':user_id', $_SESSION['validated'], PDO::PARAM_INT); // binds the user id stored in the session variable to the placeholder in the sql query
                    $stmt->bindParam(':quote_id', $quoteID, PDO::PARAM_INT); // binds the quote id to the placeholder in the sql query
                    $stmt->execute(); // executes the sql query
                    echo json_encode(["success" => "The quote was successfully added to favorites!"]); // sends success json response
                } else { // quote isn't favorited
                    $q = "DELETE FROM favorites WHERE user_id = :user_id AND quote_id = :quote_id"; // initializes the sql query string to delete the quote from the users favourites
                    $stmt = $dbh->prepare($q); // prepares the sql query string for execution
                    $stmt->bindParam(':user_id', $_SESSION['validated'], PDO::PARAM_INT); // binds the user id stored in the session to the placeholder in the query
                    $stmt->bindParam(':quote_id', $quoteID, PDO::PARAM_INT); // binds the quote id to the placeholder in the query
                    $stmt->execute(); // execute the query
                    echo json_encode(["success" => "The quote was successfully removed from favorites!"]); // send success response as json          
                }
            } catch (Exception $ex) {
                echo json_encode(["error" => "Uh oh! An unexpected error occurred!"]); // send unexpected exception response
            }
        }
    }

    // Logout
    if (isset($_POST['action']) && $_POST['action'] == 'logout') { // checks if the incoming POST 'action' variable is set and if the request is to logout
        echo "Logged out!"; // send text back to indicate the user has logged out
        session_unset(); // unsets all session variables
        session_destroy(); // destroys the session
        // header("Location: " . $_SERVER['PHP_SELF']); --> This messes things up a bit. Cause UI is reloaded regardless..
    }
}


/**
 * This function is responsible for creating a Bootstrap 5 card that contains a quote, and the author of the quote.
 * Depending on whether the user is logged in or not, they will be able to favorite quotes. Each of the cards will be
 * displayed onto the webpage dynamically, and the colors of the cards are randomly chosen. 
 * 
 * @param string $author The author of the quote
 * @param string $quote The quote itself (the quote text)
 * @param int $quote_id The quotes id
 * @return string $html A string containing html to build a quote card
 */
function createCard($author, $quote, $quote_id) {
    global $dbh; // declares the dbh as global allowing for access within the body of the function
    if (!empty($author) && !empty($quote)) { // checks that the author and quote variables are not empty

        $stmt = $dbh->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = :user_id AND quote_id = :quote_id"); // prepares the sql query that will check if the user has already favourited the specific quote
       
        $stmt->bindParam(':user_id', $_SESSION['validated'], PDO::PARAM_INT); // binds the user id stored in the session to the place holder in the query
        $stmt->bindParam(':quote_id', $quote_id, PDO::PARAM_INT); // binds the quote id to the place holder in the query
        $stmt->execute(); // executes the query
        $isFavorited = $stmt->fetchColumn() > 0; // checks if the user has favourited the quote based on whether or not the query returns a count more than 0
        
        $headerColors = ['red', 'blue', 'gray', 'green', 'orange']; // array of possible colors to be chosen from for the card header
        $randomHeaderColor = $headerColors[array_rand($headerColors)]; // uses the random array method to pick a random key from the array

        $bodyColors = ['white', 'yellow', 'lightpeach', 'lavender', 'cyan', 'salmon', 'palegreen']; // array of possible colors for card body
        $randomBodyColor = $bodyColors[array_rand($bodyColors)]; // uses random array method to pick a random key from array effectively picking a random color

        // generates the html for the quote card visible to all users, dynamically loading it with a quote and its author
        $html = '<div class="card mt-5 mb-3 a4card w-100">
                    <div class="card-header text-white" style="background-color: ' . $randomHeaderColor . ';"><strong><em>' . htmlspecialchars($author) . '</em></strong></div>
                    <div class="card-body text-dark d-flex align-items-center" style="background-color: ' . $randomBodyColor . ';">
                        <p class="card-text w-100"><em>' . htmlspecialchars($quote) . '</em></p>
                    </div>';

        if (isset($_SESSION['validated'])) { // checks if the session variable 'validated' is set indicating the user is logged in
            $checked = $isFavorited ? 'checked' : ''; // determines whether or not the user has favorited a quote. if they have, 'checked' is stored in the variable
            
            // constructs the html for the part of the quote card that allows logged in users to favorite quotes
            $html .= '<div class="form-check form-switch">
                        <div class="d-flex justify-content-start">
                            <input href="#" type="checkbox" class="form-check-input" id="c' . $quote_id . '"' . $checked . ' onclick="buttonFav(' . $quote_id . ',document.getElementById(\'c' . $quote_id . '\').checked );" ' . $checked . '>
                            <label class="mr-2">⭐</label>
                        </div>
                      </div>';
        }
        $html .= '</div></div>'; // closing tags for the quote card regardless of if the user is logged in
        return $html; // returns the html that makes up the quote card
    }
    return ''; // return nothing if no quotes or authors are available
}
?>