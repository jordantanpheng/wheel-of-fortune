<template>
  <div>
	<div v-if="inputUsername">
		<br><br>
		<form @submit.prevent="sendUsername()">
			<input type="text" placeholder="Enter your username" v-model="username">
			<br><br>
			<button class="btn btn-warning">Set username</button>
			<br><br>
			<div class="server">{{ serverMessage }}</div>
		</form>		
	</div>
	<div class="waitingPlayers" v-if="waitingPlayers">
		<br><br>
		<p>Waiting for more players: {{ numberOfPlayers }} / 8</p>
		<li v-for="username in playersList" :key="username.player">
           {{ username.player }}
        </li>
		<br>
		<form @submit.prevent="startGame()">
			<button :disabled="startButtonDisabled" class="btn btn-warning">Start Game</button>
		</form>
	</div>
	<div v-if="gameStarted">
		<br><br>
		<div class="puzzle">{{ gameData }}</div>
		<br><br>
		<form @submit.prevent="sendLetter()">
			<input type="text" placeholder="Enter a letter" v-model="letter.letter">
			<br><br>
			<button class="btn btn-warning">Send letter</button>
			<br><br>
			<div class="server">{{ serverMessage }}</div>
		</form>
	</div>
  </div>
</template>

<script>
export default {
  data() {
	return {
		inputUsername: true,
		waitingPlayers: false,
		startButtonDisabled: true,
		gameStarted: false,
		playersList: "",
		numberOfPlayers: "",
		username: "",
		gameData: "",
		gameStatus: "",
		letter: {},
		serverMessage: ""
	} 
  },  
  methods: {
	sendUsername() {
		this.$cookies.set('username', this.username);
		this.$http.post(window.location.pathname + '/' + this.username);
		this.waitingPlayers = true;
		this.gameStarted = false;
		this.inputUsername = false;	
	},
	startGame() {
		// Update game status
		this.$http.post(window.location.pathname + '/status/start');	
		this.redirectFromLobbyToGame();
		this.waitingPlayers = false;
		this.gameStarted = true;
		this.inputUsername = false;
	},
	sendLetter() {
		this.$http.post(window.location.pathname, this.letter).then(response => {
			this.serverMessage = response.body;
		});
	},
	refreshPlayersList() {
		// We first need to check if game status changed
		this.$http.get(window.location.pathname + '/status').then(response => {
			this.gameStatus = JSON.parse(JSON.stringify(response.body));
		});
		if (this.gameStatus.status == 'gameStarted') {
			this.redirectFromLobbyToGame();
		}
		// If not we get players list
		this.$http.get(window.location.pathname + '/players').then(response => {
			this.playersList = response.body;
			this.numberOfPlayers = this.playersList.length;
			if (this.numberOfPlayers > 1) {
				this.startButtonDisabled = false;
			}		
		});
	},
	refreshGameState() {
		this.$http.get(window.location.pathname).then(response => {
			this.gameData = response.body;
		});
	},
	redirectFromLobbyToGame() {
		var gameLink = window.location.pathname.split('/');
		gameLink[2] = 'play';
		gameLink = gameLink.join('/');
		window.location.replace(gameLink);
	}
  },
  mounted(){
	if (window.location.pathname.split('/')[2] == 'lobby' || window.location.pathname.split('/')[2] == 'play') {
		// Check game status
		this.$http.get(window.location.pathname + '/status').then(response => {
			this.gameStatus = JSON.parse(JSON.stringify(response.body));
			// If we are waiting for players to come
			if (this.gameStatus.status == 'waitingPlayers') {
				this.$emit('startGameInstance');
				// Check if player has set his username 
				if (this.$cookies.isKey('username') === true) {
					this.inputUsername = false;
					this.waitingPlayers = true;
					this.gameStarted = false;
				}
				// Refresh players list in lobby every second
				var that1 = this;
				setInterval(function(){that1.refreshPlayersList();}, 1000);
			// If the game has already started
			} else if (this.gameStatus.status == 'gameStarted') {
				// Refresh game status every second
				var that2 = this;
				setInterval(function(){that2.refreshGameState();}, 1000);
				// Check if player has set his username
				if (this.$cookies.isKey('username') === true) {
					this.inputUsername = false;
					this.waitingPlayers = false;
					this.gameStarted = true;
					// Get Game Data
					this.$http.get(window.location.pathname).then(response => {
						this.gameData = response.body;
					});
				} else {
					window.location.replace('/');
				}
				// Redirect the user if he is in the lobby
				if (window.location.pathname.split('/')[2] == 'lobby') {
					this.redirectFromLobbyToGame();
				}
				this.$emit('startGameInstance');
			// If the game has finished 
			} else {
				window.location.replace('/');
			}
		});
	} else {
		this.$cookies.remove("username");
	}
  }
}
</script>

<style>
#app {
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-align: center;
  color: #2c3e50;
  margin-top: 60px;
}
div.puzzle {
  font-size: 75px;
}
div.server {
  color: blue;
}
div.waitingPlayers {
  text-align: center;
  list-style-type: none;
}
</style>
