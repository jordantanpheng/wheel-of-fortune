<template>
  <div id="app">
    <img alt="WheelOfFortune logo" src="./assets/logo.png">
	<div v-if="inFrontPage">
		<br><br><br>
		<h1>Welcome to the Wheel Of Fortune online game !</h1>
		<br><br>
		<button @click.prevent="createNewGame()" class="btn btn-warning">Create Game</button>
		<br><br>
		<a v-bind:href="'/front/lobby/'+ game.id">{{ lobbyLink }}</a>
	</div>
	<div v-show="inLobby">
		<game-instance @startGameInstance="startGameInstance()"></game-instance>
	</div>
  </div>
</template>

<script>
import GameInstance from "./components/GameInstance.vue";
export default {
  name: 'App',
  components: {GameInstance},
  data() {
	return {
		game: "",
		lobbyLink: "",
		inFrontPage: true,
		inLobby: false
	}
  },
  methods: {
	createNewGame() {
		const newGame = { id: Math.random().toString(36).substr(2, 9) };
		this.$http.post('create', newGame).then(response => {
			this.game = response.body;
			this.lobbyLink = 'Join Game';
		});
    },
	startGameInstance() {
		this.inFrontPage = false;
		this.inLobby = true;
	}
  },
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
</style>
