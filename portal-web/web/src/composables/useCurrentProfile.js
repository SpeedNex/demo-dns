import { computed } from 'vue'
import { useRoute } from 'vue-router'

export function useCurrentProfile() {
    const route = useRoute()

    const currentProfileId = computed(() => {
        return route.query.profile_id || localStorage.getItem('current_profile_id') || ''
    })

    return { currentProfileId }
}