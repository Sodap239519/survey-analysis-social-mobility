<template>
  <div>
    <h2 class="mb-6" style="font-size:1.25rem;font-weight:700">📊 ภาพรวม</h2>
    <p class="text-muted">ยินดีต้อนรับ <strong>{{ auth.user?.name }}</strong></p>
    <p class="text-muted text-sm mt-2">ใช้เมนูด้านซ้ายเพื่อจัดการข้อมูล</p>
    <RouterLink to="/" class="btn btn-primary mt-4" style="display:inline-block">
      ดูแดชบอร์ดสาธารณะ →
    </RouterLink>
  </div>
</template>

<script setup>
import { useAuthStore } from '../../stores/auth'
const auth = useAuthStore()
</script>
