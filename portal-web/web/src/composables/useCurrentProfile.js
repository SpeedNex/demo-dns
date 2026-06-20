import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

export function useCurrentProfile() {
    const route = useRoute()
    const router = useRouter()

    const currentProfileUid = computed(() => {
        if (route.params.profile_id) {
            return route.params.profile_id
        }
        if (route.query.profile_id) {
            return route.query.profile_id
        }
        return localStorage.getItem('current_profile_id') || ''
    })

    watch(
        () => route.params.profile_id,
        (newUid) => {
            if (newUid) {
                localStorage.setItem('current_profile_id', newUid)
            }
        }
    )

    return { currentProfileUid, currentProfileId: currentProfileUid, route, router }
}
