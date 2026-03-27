<script setup>
import { Link, usePage } from '@inertiajs/vue3'
defineProps({ users: Object, roles: Array })
</script>

<template>
  <div style="padding:20px">
    <h1>Usuarios</h1>
    <Link class="btn" :href="route('admin.users.create')">Crear usuario</Link>

    <table style="width:100%;margin-top:12px">
      <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th></th></tr></thead>
      <tbody>
        <tr v-for="u in users.data" :key="u.id">
          <td>{{ u.name }}</td>
          <td>{{ u.email }}</td>
          <td>{{ u.roles?.[0]?.name ?? '-' }}</td>
          <td>
            <Link :href="route('admin.users.edit', u.id)">Editar</Link>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>