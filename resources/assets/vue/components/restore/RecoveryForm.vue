<template>
    <div>
        <div class="cabinet-profile-control" v-if="errors.length > 0">
            <div v-for="error in errors" class="cabinet-error">
                {{ error }}
            </div>
        </div>

        <div class="cabinet-profile-control">
            <label class="cabinet-profile-label">E-mail</label>
            <input autofocus type="email" class="form-input" name="email" placeholder="Введите email" v-model="email">
        </div>
        <h3>И/Или</h3>
        <div class="cabinet-profile-control danger">
            <label class="cabinet-profile-label">Имя пользователя</label>
            <input type="username" class="form-input" name="password" v-model="username" placeholder="Введите имя пользователя">
            <!--<div class="control-msg">-->
                <!--Test-->
            <!--</div>-->
        </div>

        <div class="cabinet-profile-submit">
            <button @click="send()" type="button" class="button button-blue button-round">
                Отправить код восстановления
            </button>
        </div>
    </div>
</template>

<script>
    export default {
        name: 'recover-form',
        data() {
            return {
                username: '',
                email: '',
                errors: []
            }
        },
        methods: {
            send() {
                this.errors = [];
                if (this.username || this.email) {
                    var url = '/recover-code';
                    axios.post(url, {
                        username: this.username,
                        email: this.email
                    })
                        .then((response) => {
                            console.log(response);
                            if (response.data.type === 'success') {
                                this.$parent.isRecovery = false;
                            }
                            else{
                                response.data.msg.forEach((error) => {
                                    this.errors.push(error);
                                })
                            }
                        })
                        .catch((error) => {
                            console.log(error);
                            if (error && error.hasOwnProperty('response') && error.response.hasOwnProperty('status') && error.response.status == 422) {
                                for(var index in error.response.data) {
                                    if (error.response.data.hasOwnProperty(index)) {
                                        error.response.data[index].forEach((error) => {
                                            this.errors.push(error);
                                        })
                                    }
                                }
                            }
                            else {
                                alert('Произошла ошибка во время выполнения запроса');
                            }
                        })
                }
                else {
                    this.errors.push('Для восстановление пароля требуется ввести email и/или имя пользователя');
                }
            }
        }
    }
</script>

<style scoped lang="scss">
    .control-msg {
        margin-top: 7px;
    }

    .danger {
        .control-msg {
            color: #F45B5B;
        }
    }
</style>