import axios from 'axios';

const NotificationService = {
    getNotifications: async () => {
        const config = {
            method: 'get',
            maxBodyLength: Infinity,
            url: 'http://backend.itask.intelligrp.com/notification-list',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer eyJpdiI6IlMremptQXorNWlTZEJuV3djc1RkOXc9PSIsInZhbHVlIjoiZmY4OFp0NEhScFppMEdpeHNIam15aWxqdVQvVisxLzlUVGovM0tXTk8xcmFYMEpzWDhjWDArNUtJWld4QjJyUndLSkRpMnd2WHgzdUJGWGxtVWZKS2VsbUF3OUM2dGNMNGk3SjFxdmJUQjRUQzU1dG1pZ0pFNndXSkZ4SzZXSFMiLCJtYWMiOiI2ZDk2YzBlZjI5MDAxN2YzNTRjMTljNzgzNjQ4ZTZlNjgxMTljODc5MTNlZjQ5YWNjMTIxZjJlNjg4MjllNmVjIiwidGFnIjoiIn0',
                'X-CSRF-TOKEN': 'TOPEkhHmw5zZgpuhmhVahdizlWg7UqqNjSpXJsS1',
                'Cookie': 'XSRF-TOKEN=eyJpdiI6InMxVTNlMTBnS3E2UGNLc2xDd3NENUE9PSIsInZhbHVlIjoiRi9aRk8yRG16MGxJbVRlQmwxNU8yVmNOVG9veXI2OFc5RU85UVFEQXRzajBvRlIwN1NLakx6d3lON2V6Uys4eEwzS2NnVGE3dUs3amZWRjNPL3lEMFZEaG4vR1kzMnZFL1hPY0d2eDlmb3NkQWl1RVpHb25iSlFJdnFlNERsS1IiLCJtYWMiOiIzZmI5MjkyOTgwMDEwMmQ2MTk4YjgwZmQ0NDkwYTgzNTA4ZTI3MmZjZDE4YWQ3OTRkZDM3NzUwYzhiYzJmZGI5IiwidGFnIjoiIn0%3D; itask_session=eyJpdiI6IlVnVmVqbHdNeTNmQWJJb1R0LzdvVHc9PSIsInZhbHVlIjoidExQL1Joa2dFRy8rYW15WXE0RWJWMnd5YzFvbm41T3VLdkJ0YzVLUkt3ZFhtV1c0NVFSMXEwdmUwSUNNVHkxT240MWdxYVB5R0R3UjgrNDlCam55YU1LRndzWHFNRzZkSXkzMFJ1QlhZVi9MbEl6K0RlWFB0VFk4YWVQYnVnRE0iLCJtYWMiOiI0OGNmZTU5MGVhMmZlMDUxOWFlYzdmM2ZiOTAwMmE0ZWJlYmU5NmQ4YTlhNmVmZDkyZWU5OTg1NjI1ZWExNGM5IiwidGFnIjoiIn0%3D',
            },
        };

        try {
            const response = await axios.request(config);
            return response.data;
        } catch (error) {
            console.error("Error fetching notifications:", error);
            throw error;
        }
    },
};

export default NotificationService;