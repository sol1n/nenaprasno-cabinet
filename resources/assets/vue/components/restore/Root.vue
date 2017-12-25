<template>
    <div class="cabinet-profile">
        <div class="cabinet-profile-card m-t-lg m-b-lg" style="max-width: 570px; margin-left: auto; margin-right: auto;">
            <div class="cabinet-profile-title">
                Восстановление пароля
            </div>
            <recovery-form v-if="isRecovery"></recovery-form>
            <restore-form v-else></restore-form>
        </div>
    </div>
</template>

<script>
    import RecoveryForm from './RecoveryForm.vue';
    import RestoreForm from './RestoreForm.vue';

    export default {
        name: 'restore',
        components: {
            RecoveryForm,
            RestoreForm
        },
        data() {
            return {
                isRecovery: true,
                email: '',
                username: ''
            }
        },
        mounted() {
            this.isRecovery = true;
        }
    }
</script>
