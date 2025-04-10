import axios from 'axios';

const ProjectService = {
    getProjects: async (is_completed) => {
        const data = JSON.stringify({
            is_completed: is_completed,
        });

        const config = {
            method: 'post',
            maxBodyLength: Infinity,
            url: 'http://backend.itask.intelligrp.com/projects',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer your-token', // Replace with your actual token
                'X-CSRF-TOKEN': 'your-csrf-token', // Replace with your actual CSRF token
                'Cookie': 'XSRF-TOKEN=eyJpdiI6Ill6T2tRUFMyUWc5a2dhUTF6TCs5Y3c9PSIsInZhbHVlIjoibXdQTEtmNDhPMml3ZTgrTjJGaXJTZXJWcWNkRWxCUWdqSTRFM2ZnYmphVFJIL2drWGpHVm9nMmh2dDVvTWFobkR1eU1RbFVZc3I5dndaTVV6dW1QVURoVWV2TGF6dGhNTlZ5NDlYdFhBNVc1bE5OVm5WRDlQcTdrei9YYlNrQjYiLCJtYWMiOiJiOTdiYjlmZWMxNTE1MDljZjlhZDNlM2FiMjcyNGM1Y2QwYzNiOThkNWUwYzI2NWZhZjQ2MDJiMDJiMTZiMzY2IiwidGFnIjoiIn0%3D; itask_session=eyJpdiI6Ii9GSEpXb3M3a1Q3Z0xGWmxJYnM4NVE9PSIsInZhbHVlIjoiNlE2MCtWdlJiekViODJ4Tkt3Z08wUWxIcW15NERtRjR6Uy9rekJjY1loaHhOMzIrNnpCbWNJSmJpdmhuTHpwUUphTDFwQ3JCeHB1TlYrUlRyL2hUOTE1K3VSditZUVdlL1liNzQ1L2xlQ2FKMGx1dDFjTjhtdVludmVxTmlSZVIiLCJtYWMiOiJlOWUyZTU2Nzc0NDM4ZGM2Y2NjMmZhOGY1YzQ4MGRjZGI0ZTI5ZWEyMDQxNjVhZjcyZGM2MzdlZDMzYmI4MmQ3IiwidGFnIjoiIn0%3D', // Replace with your actual cookies
            },
            data: data,
        };

        try {
            const response = await axios.request(config);
            return response.data;
        } catch (error) {
            console.error("Error fetching projects:", error);
            throw error;
        }
    },
};

export default ProjectService;