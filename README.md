# mk-point-staker
> This plugin integrates myCRED, SportsPress, and Profile managers (Ultimate Member as main choice) to allow users stake points in online matches against each other. A stake is created by a user, and then a sportspress event is automatically created between the author and any other user who accepts the stake first. The stake amount decided by the author is deducted as points from the involved users. The winner is decided when the match result is updated. Results ending in a draw causes a refund to both parties.. There's possibility of cancelling created and unaccepted stakes.
Beautiful UI elements and designs.

# Data Flow
``` sequenceDiagram
       JS->>PHP: submit_stake (stake-form-handler.php)
       PHP->>JS: success/error
       JS->>PHP: accept_stake (pairing.php)
       PHP->>SportsPress: create_event (sportspress-integration.php) 